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

$json = ['result' => 'error', 'message' => 'Use app/reservas_save.php como endpoint de criação de reserva.'];

if (@$_POST['token'] == TOKEN) {
	$json = ['result' => 'error'];

	if(empty($_POST['origem'])) {
		$_POST['origem'] = 'app';
	}else{
		$_POST['origem'] = $_POST['origem'];
	}

	$duplicada = reserva_existe_duplicada_pendente($db, $_POST);
	if ($duplicada) {
		$json = [
			'result' => 'error',
			'message' => 'Já existe uma reserva pendente com os mesmos dados. Aguarde a aprovação da reserva.',
			'reserva' => $duplicada,
		];
	} elseif (defined('SIS_ATIVO') && SIS_ATIVO) {
		define('RESERVAS_SAVE_BOOTSTRAP', true);
		try {
			include __DIR__ . '/reserva_sis.php';
		} catch (Throwable $e) {
			$json = ['result' => 'error', 'message' => $e->getMessage()];
		}
	} else {
		include __DIR__ . '/reserva_comum.php';
	}
}

echo json_encode($json, JSON_UNESCAPED_UNICODE);
