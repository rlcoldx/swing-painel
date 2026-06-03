<?php
include('../config/config.php');

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");

$_POST = json_decode(file_get_contents("php://input"), true);

if (@$_POST['token'] == TOKEN) {

	$dados = [$_POST['id_usuario'], $_POST['codigo_reserva']];
	$sql_check = $db->prepare("SELECT * FROM reservas WHERE id_usuario = ? AND codigo_reserva = ?");
	$sql_check->execute($dados);
	$check = $sql_check->fetch(PDO::FETCH_ASSOC);

	if (!$check) {
		echo json_encode(['result' => 'REFUSED']);
		exit;
	}

	if ($check['status_reserva'] === 'Pendente' && !empty($check['date_create'])) {
		$limite = strtotime($check['date_create'] . ' +10 minutes');
		if (time() > $limite) {
			$upd = $db->prepare("UPDATE reservas SET status_reserva = 'Cancelado' WHERE id = ?");
			$upd->execute([$check['id']]);
			$check['status_reserva'] = 'Cancelado';
		}
	}

	if (($check['integracao'] ?? '') === 'sis' && $check['status_reserva'] === 'Pendente') {
		require_once __DIR__ . '/reserva_check_sis.php';
		reserva_check_sis_sync($db, $check);
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
} else {
	echo 'Sem autorização';
}
