<?php
include('../config/config.php');

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE');
header('Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method');
header('Content-Type: application/json; charset=UTF-8');

$_POST = json_decode(file_get_contents('php://input'), true);

if (($_POST['token'] ?? '') !== TOKEN) {
    http_response_code(401);
    echo json_encode(['result' => 'error', 'message' => 'Sem autorização'], JSON_UNESCAPED_UNICODE);
    exit;
}

$idUsuario = (int) ($_POST['id_usuario'] ?? 0);
if ($idUsuario <= 0) {
    http_response_code(400);
    echo json_encode(['result' => 'error', 'message' => 'id_usuario inválido'], JSON_UNESCAPED_UNICODE);
    exit;
}

$resumo = fidelidade_resumo_usuario($db, $idUsuario);
$regras = fidelidade_regras_publicas();

echo json_encode([
    'result' => 'success',
    'saldo' => $resumo['saldo'],
    'total_ganho' => $resumo['total_ganho'],
    'total_gasto' => $resumo['total_gasto'],
    'regras' => $regras,
    'programa_ativo' => fidelidade_tabelas_existem($db),
], JSON_UNESCAPED_UNICODE);
