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

echo json_encode([
    'result' => 'success',
    'regras' => fidelidade_regras_publicas(),
    'programa_ativo' => fidelidade_tabelas_existem($db),
], JSON_UNESCAPED_UNICODE);
