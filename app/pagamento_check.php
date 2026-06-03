<?php 
include('../config/config.php');

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");

$_POST = json_decode(file_get_contents("php://input"), true);

if (@$_POST['token'] == TOKEN) {

	$dados = [$_POST['id_usuario'], $_POST['codigo_reserva']];
	$sql_check = $db->prepare(
		"SELECT r.*, p.pagamento_status FROM reservas AS r
		 LEFT JOIN pagamentos AS p ON p.id_reserva = r.id
		 WHERE r.id_usuario = ? AND r.codigo_reserva = ?"
	);
	$sql_check->execute($dados);
	$check = $sql_check->fetch(PDO::FETCH_ASSOC);

	if ($check && $check['status_reserva'] === 'Recusado') {
		if (($check['integracao'] ?? '') === 'sis' && !empty($check['id_reserva_sis'])) {
			sis_cancelar_reserva((int) $check['id_reserva_sis']);
			$upd = $db->prepare('UPDATE reservas SET status_sis = 8 WHERE id = ?');
			$upd->execute([$check['id']]);
		}
		echo json_encode(['result' => 'REFUSED']);
		exit;
	}

	if ($check && $check['pagamento_status'] !== 'pending' && $check['pagamento_status'] !== null) {
		$json = ['result' => 'OK'];
	} else {
		$json = ['result' => 'WAIT'];
	}

	echo json_encode($json);
} else {
	echo 'Sem autorização';
}
