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

if (!fidelidade_tabelas_existem($db)) {
    echo json_encode([
        'result' => 'success',
        'movimentacoes' => [],
        'programa_ativo' => false,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$limite = (int) ($_POST['limite'] ?? 50);
$limite = max(1, min(200, $limite));

$st = $db->prepare(
    'SELECT id, pontos, tipo, descricao, id_reserva, id_resgate, criado_em
     FROM fidelidade_movimentacao
     WHERE id_usuario = ?
     ORDER BY criado_em DESC, id DESC
     LIMIT ' . $limite
);
$st->execute([$idUsuario]);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as &$r) {
    $r['id'] = (int) $r['id'];
    $r['pontos'] = (int) $r['pontos'];
    $r['id_reserva'] = $r['id_reserva'] !== null ? (int) $r['id_reserva'] : null;
    $r['id_resgate'] = $r['id_resgate'] !== null ? (int) $r['id_resgate'] : null;
}

echo json_encode([
    'result' => 'success',
    'movimentacoes' => $rows,
    'programa_ativo' => true,
], JSON_UNESCAPED_UNICODE);
