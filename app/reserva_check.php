<?php
include('../config/config.php');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE');
header('Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
	exit;
}

$_POST = json_decode(file_get_contents('php://input'), true) ?: [];

if (@$_POST['token'] != TOKEN) {
	echo json_encode(['result' => 'error', 'message' => 'Sem autorização']);
	exit;
}

$dados = [$_POST['id_usuario'], $_POST['codigo_reserva']];
$sql_check = $db->prepare('SELECT * FROM reservas WHERE id_usuario = ? AND codigo_reserva = ?');
$sql_check->execute($dados);
$check = $sql_check->fetch(PDO::FETCH_ASSOC);

if (!$check) {
	echo json_encode(['result' => 'REFUSED']);
	exit;
}

if ($check['status_reserva'] === 'Pendente' && !empty($check['date_create'])) {
	$limite = strtotime($check['date_create'] . ' +30 minutes');
	if (time() > $limite) {
		$upd = $db->prepare("UPDATE reservas SET status_reserva = 'Cancelado' WHERE id = ?");
		$upd->execute([$check['id']]);
		$check['status_reserva'] = 'Cancelado';
	}
}

if (($check['integracao'] ?? '') === 'sis' && $check['status_reserva'] === 'Pendente') {
	define('RESERVA_CHECK_BOOTSTRAP', true);
	require_once __DIR__ . '/reserva_check_sis.php';
	try {
		reserva_check_sis_sync($db, $check);
	} catch (Throwable $e) {
		echo json_encode(['result' => 'error', 'message' => $e->getMessage()]);
		exit;
	}
}

if ($check['status_reserva'] === 'Aceito') {
	$json = ['result' => 'OK'];
} elseif ($check['status_reserva'] === 'Recusado' || $check['status_reserva'] === 'Cancelado') {
	if (($check['integracao'] ?? '') === 'sis' && !empty($check['id_reserva_sis'])) {
		sis_cancelar_reserva((int) $check['id_reserva_sis']);
		$upd = $db->prepare('UPDATE reservas SET status_sis = 8 WHERE id = ?');
		$upd->execute([$check['id']]);
	}
	$json = ['result' => 'REFUSED'];
} else {
	$json = ['result' => 'WAIT'];
}

echo json_encode($json);
