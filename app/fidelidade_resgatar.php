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
$tipo = trim((string) ($_POST['tipo'] ?? ''));
$idSuite = isset($_POST['id_suite']) ? (int) $_POST['id_suite'] : null;

if ($idUsuario <= 0 || $tipo === '') {
    http_response_code(400);
    echo json_encode(['result' => 'error', 'message' => 'Informe id_usuario e tipo (suite, alimento ou bebida).'], JSON_UNESCAPED_UNICODE);
    exit;
}

$out = fidelidade_processar_resgate_app($db, $idUsuario, $tipo, $idSuite);

if (!$out['ok']) {
    http_response_code(422);
    echo json_encode([
        'result' => 'error',
        'message' => $out['message'] ?? 'Resgate não realizado',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$resumo = fidelidade_resumo_usuario($db, $idUsuario);

echo json_encode([
    'result' => 'success',
    'message' => 'Resgate registrado. Aguarde confirmação no local (sujeito à disponibilidade).',
    'id_resgate' => $out['id_resgate'],
    'pontos_debitados' => $out['pontos_debitados'],
    'saldo_atual' => $resumo['saldo'],
], JSON_UNESCAPED_UNICODE);
