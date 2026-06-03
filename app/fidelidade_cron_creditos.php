<?php
/**
 * Cron: credita pontos de fidelidade em reservas pagas que ainda não têm movimento `credito_reserva_app`.
 *
 * Chamada (GET, recomendado para agendador):
 *   GET .../app/fidelidade_cron_creditos.php?token=SEU_TOKEN
 *
 * Ou POST JSON: { "token": "SEU_TOKEN" } (mesmo TOKEN da API em config.php)
 */
include('../config/config.php');

header('Content-Type: application/json; charset=utf-8');

if (!function_exists('fidelidade_cron_creditos_reservas_pagas')) {
	include_once(dirname(__DIR__) . '/config/fidelidade.php');
}

$out = fidelidade_cron_creditos_reservas_pagas($db);
echo json_encode($out, JSON_UNESCAPED_UNICODE);