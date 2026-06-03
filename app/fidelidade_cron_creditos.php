<?php
include('../config/config.php');

header('Content-Type: application/json; charset=utf-8');

if (!function_exists('fidelidade_cron_creditos_reservas_pagas')) {
	include_once(dirname(__DIR__) . '/config/fidelidade.php');
}

$out = fidelidade_cron_creditos_reservas_pagas($db);
echo json_encode($out, JSON_UNESCAPED_UNICODE);