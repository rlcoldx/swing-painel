<?php

include('../config/config.php');

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");

$_POST = json_decode(file_get_contents("php://input"), true);

$json = "";

if (@$_POST['token'] == TOKEN) {

	$json = ['result' => 'error'];

	if (defined('SIS_ATIVO') && SIS_ATIVO) {
		include __DIR__ . '/reserva_sis.php';
	} else {
		include __DIR__ . '/reserva_comum.php';
	}
}

$json = json_encode($json);
echo $json;
