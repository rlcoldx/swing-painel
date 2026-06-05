<?php
include('../config/config.php');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE');
header('Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method');

$_POST = json_decode(file_get_contents('php://input'), true) ?: [];

if (@$_POST['token'] != TOKEN) {
	echo json_encode(['result' => 'error', 'message' => 'Sem autorização']);
	exit;
}

$dados = [$_POST['id_usuario'], $_POST['codigo_reserva']];
$sql_check = $db->prepare(
	"SELECT r.*, p.pagamento_status FROM reservas AS r
	 LEFT JOIN (
		SELECT p1.*
		FROM pagamentos p1
		INNER JOIN (
			SELECT id_reserva, MAX(id) AS id
			FROM pagamentos
			GROUP BY id_reserva
		) x ON x.id = p1.id
	 ) p ON p.id_reserva = r.id
	 WHERE r.id_usuario = ? AND r.codigo_reserva = ?"
);
$sql_check->execute($dados);
$check = $sql_check->fetch(PDO::FETCH_ASSOC);

if (!$check) {
	echo json_encode(['result' => 'REFUSED']);
	exit;
}

$idReservaSis = (int) ($check['id_reserva_sis'] ?? 0);
if (defined('SIS_ATIVO') && SIS_ATIVO && $idReservaSis > 0) {
	$sisData = sis_get_reservation($idReservaSis);
	$situation = sis_extrair_situation($sisData, (int) ($check['status_sis'] ?? 0));

	if (
		sis_situacao_e_cancelada($situation)
		&& reserva_pode_cancelar_no_sis($db, $check)
	) {
		$upd = $db->prepare("UPDATE reservas SET status_reserva = 'Cancelado', status_sis = ? WHERE id = ?");
		$upd->execute([$situation, $check['id']]);
		echo json_encode(['result' => 'REFUSED']);
		exit;
	}
}

if ($check['status_reserva'] === 'Recusado' || $check['status_reserva'] === 'Cancelado') {
	echo json_encode(['result' => 'REFUSED']);
	exit;
}

if ($check['pagamento_status'] !== 'pending' && $check['pagamento_status'] !== null) {
	echo json_encode(['result' => 'OK']);
} else {
	echo json_encode(['result' => 'WAIT']);
}
