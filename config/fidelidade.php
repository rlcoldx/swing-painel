<?php
/**
 * Programa de fidelidade — regras e operações no banco (app + painel).
 * Requer $db (PDO) definido em config.php.
 */

if (!defined('FIDELIDADE_PONTOS_POR_REAL')) {
    define('FIDELIDADE_PONTOS_POR_REAL', 1);
}
if (!defined('FIDELIDADE_RESGATE_SUITE_PONTOS')) {
    define('FIDELIDADE_RESGATE_SUITE_PONTOS', 920);
}
if (!defined('FIDELIDADE_RESGATE_ALIMENTO_PONTOS')) {
    define('FIDELIDADE_RESGATE_ALIMENTO_PONTOS', 300);
}
if (!defined('FIDELIDADE_RESGATE_BEBIDA_PONTOS')) {
    define('FIDELIDADE_RESGATE_BEBIDA_PONTOS', 150);
}

const FIDELIDADE_TIPO_CREDITO_RESERVA = 'credito_reserva_app';
const FIDELIDADE_TIPO_RESGATE_SUITE = 'debito_resgate_suite';
const FIDELIDADE_TIPO_RESGATE_ALIMENTO = 'debito_resgate_alimento';
const FIDELIDADE_TIPO_RESGATE_BEBIDA = 'debito_resgate_bebida';
const FIDELIDADE_TIPO_AJUSTE_ADMIN = 'ajuste_admin';

/**
 * Saldo atual do usuário (soma das movimentações).
 */
function fidelidade_saldo_usuario(PDO $db, int $idUsuario): int
{
    if (!fidelidade_tabelas_existem($db)) {
        return 0;
    }
    $st = $db->prepare('SELECT COALESCE(SUM(pontos), 0) AS s FROM fidelidade_movimentacao WHERE id_usuario = ?');
    $st->execute([$idUsuario]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return (int) ($row['s'] ?? 0);
}

/**
 * Credita pontos quando o pagamento da reserva (via app) é aprovado.
 * Idempotente por reserva + tipo. R$ 1,00 pago = 1 ponto (parte inteira).
 * Reservas sem id_usuario ou valor pago zero não pontuam.
 */
function fidelidade_tabelas_existem(PDO $db): bool
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    try {
        $db->query('SELECT 1 FROM fidelidade_movimentacao LIMIT 1');
        $cache = true;
    } catch (Throwable $e) {
        $cache = false;
    }
    return $cache;
}

function fidelidade_creditar_por_pagamento_aprovado(PDO $db, int $idReserva, float $valorPagoReal): bool
{
    if (!fidelidade_tabelas_existem($db) || $idReserva <= 0) {
        return false;
    }

    $st = $db->prepare('SELECT id, id_usuario, valor_reserva, codigo_reserva FROM reservas WHERE id = ? LIMIT 1');
    $st->execute([$idReserva]);
    $reserva = $st->fetch(PDO::FETCH_ASSOC);
    if (!$reserva || empty($reserva['id_usuario'])) {
        return false;
    }

    $idUsuario = (int) $reserva['id_usuario'];
    $pontos = (int) floor(max(0, $valorPagoReal) * FIDELIDADE_PONTOS_POR_REAL);
    if ($pontos <= 0) {
        return false;
    }

    $dup = $db->prepare(
        'SELECT id FROM fidelidade_movimentacao WHERE id_reserva = ? AND tipo = ? LIMIT 1'
    );
    $dup->execute([$idReserva, FIDELIDADE_TIPO_CREDITO_RESERVA]);
    if ($dup->fetch()) {
        return false;
    }

    $desc = sprintf(
        'Pontos por reserva paga (cód. %s)',
        $reserva['codigo_reserva'] ?? (string) $idReserva
    );

    try {
        $ins = $db->prepare(
            'INSERT INTO fidelidade_movimentacao (id_usuario, pontos, tipo, descricao, id_reserva, id_resgate)
             VALUES (?, ?, ?, ?, ?, NULL)'
        );
        $ins->execute([$idUsuario, $pontos, FIDELIDADE_TIPO_CREDITO_RESERVA, $desc, $idReserva]);
    } catch (Throwable $e) {
        return false;
    }

    return true;
}

/**
 * Resumo para API / painel.
 */
function fidelidade_resumo_usuario(PDO $db, int $idUsuario): array
{
    if (!fidelidade_tabelas_existem($db)) {
        return ['saldo' => 0, 'total_ganho' => 0, 'total_gasto' => 0];
    }
    $st = $db->prepare(
        'SELECT 
            COALESCE(SUM(pontos), 0) AS saldo,
            COALESCE(SUM(CASE WHEN pontos > 0 THEN pontos ELSE 0 END), 0) AS total_ganho,
            COALESCE(SUM(CASE WHEN pontos < 0 THEN -pontos ELSE 0 END), 0) AS total_gasto
         FROM fidelidade_movimentacao
         WHERE id_usuario = ?'
    );
    $st->execute([$idUsuario]);
    $row = $st->fetch(PDO::FETCH_ASSOC) ?: ['saldo' => 0, 'total_ganho' => 0, 'total_gasto' => 0];

    return [
        'saldo' => (int) $row['saldo'],
        'total_ganho' => (int) $row['total_ganho'],
        'total_gasto' => (int) $row['total_gasto'],
    ];
}

/**
 * Informações estáticas de regras para o app (texto + números).
 */
function fidelidade_regras_publicas(): array
{
    return [
        'pontos_por_real' => FIDELIDADE_PONTOS_POR_REAL,
        'resgate_suite_pontos' => FIDELIDADE_RESGATE_SUITE_PONTOS,
        'resgate_alimento_pontos' => FIDELIDADE_RESGATE_ALIMENTO_PONTOS,
        'resgate_bebida_pontos' => FIDELIDADE_RESGATE_BEBIDA_PONTOS,
        'valor_ponto_reais' => 0.25,
        'pontos_por_real_em_resgate' => 4,
        'mensagem' => 'Faça até 5 reservas pelo aplicativo, junte 920 pontos e troque por uma nova reserva da mesma suíte.',
        'regras' => [
            'Acúmulo exclusivo em reservas de suíte feitas pelo aplicativo.',
            'R$ 1,00 gasto em reserva (pagamento aprovado) = 1 ponto.',
            '920 pontos = 1 resgate de suíte da mesma categoria (conforme disponibilidade).',
            'Resgates apenas pelo aplicativo; pontos não são transferíveis nem convertidos em dinheiro.',
            'Valores resgatados com pontos não geram novos pontos.',
            'Resgates não acumuláveis com promoções ativas (validação operacional).',
        ],
    ];
}

/**
 * Verifica se o usuário já teve reserva na suíte informada (requisito para resgate de suíte).
 */
function fidelidade_usuario_tem_reserva_na_suite(PDO $db, int $idUsuario, int $idSuite): bool
{
    if (!fidelidade_tabelas_existem($db)) {
        return false;
    }
    $st = $db->prepare(
        'SELECT id FROM reservas WHERE id_usuario = ? AND id_suite = ? LIMIT 1'
    );
    $st->execute([$idUsuario, $idSuite]);
    return (bool) $st->fetch();
}

/**
 * Resgate pelo app: debita pontos e registra pedido operacional.
 * @return array{ok:bool,message?:string,id_resgate?:int}
 */
function fidelidade_processar_resgate_app(
    PDO $db,
    int $idUsuario,
    string $tipo,
    ?int $idSuite = null
): array {
    if (!fidelidade_tabelas_existem($db)) {
        return ['ok' => false, 'message' => 'Programa de fidelidade ainda não está ativo no servidor.'];
    }
    $tipo = strtolower($tipo);
    $custo = 0;
    $tipoMov = '';
    $label = '';

    if ($tipo === 'suite') {
        $custo = FIDELIDADE_RESGATE_SUITE_PONTOS;
        $tipoMov = FIDELIDADE_TIPO_RESGATE_SUITE;
        $label = 'Resgate: reserva da mesma suíte';
        if (!$idSuite || $idSuite <= 0) {
            return ['ok' => false, 'message' => 'Informe a suíte para o resgate.'];
        }
        if (!fidelidade_usuario_tem_reserva_na_suite($db, $idUsuario, $idSuite)) {
            return ['ok' => false, 'message' => 'Resgate de suíte disponível apenas para suítes que você já reservou pelo app.'];
        }
    } elseif ($tipo === 'alimento') {
        $custo = FIDELIDADE_RESGATE_ALIMENTO_PONTOS;
        $tipoMov = FIDELIDADE_TIPO_RESGATE_ALIMENTO;
        $label = 'Resgate: alimentação';
    } elseif ($tipo === 'bebida') {
        $custo = FIDELIDADE_RESGATE_BEBIDA_PONTOS;
        $tipoMov = FIDELIDADE_TIPO_RESGATE_BEBIDA;
        $label = 'Resgate: bebida';
    } else {
        return ['ok' => false, 'message' => 'Tipo de resgate inválido. Use suite, alimento ou bebida.'];
    }

    $saldo = fidelidade_saldo_usuario($db, $idUsuario);
    if ($saldo < $custo) {
        return ['ok' => false, 'message' => 'Saldo de pontos insuficiente para este resgate.'];
    }

    try {
        $db->beginTransaction();

        $insR = $db->prepare(
            'INSERT INTO fidelidade_resgate (id_usuario, tipo, pontos, id_suite, status, observacao)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $tipoEnum = $tipo === 'suite' ? 'suite' : ($tipo === 'alimento' ? 'alimento' : 'bebida');
        $obs = 'Solicitado pelo app. Atendimento sujeito à disponibilidade.';
        $insR->execute([
            $idUsuario,
            $tipoEnum,
            $custo,
            $tipoEnum === 'suite' ? $idSuite : null,
            'pendente',
            $obs,
        ]);
        $idResgate = (int) $db->lastInsertId();

        $insM = $db->prepare(
            'INSERT INTO fidelidade_movimentacao (id_usuario, pontos, tipo, descricao, id_reserva, id_resgate)
             VALUES (?, ?, ?, ?, NULL, ?)'
        );
        $insM->execute([
            $idUsuario,
            -$custo,
            $tipoMov,
            $label,
            $idResgate,
        ]);

        $db->commit();
        return ['ok' => true, 'id_resgate' => $idResgate, 'pontos_debitados' => $custo];
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        return ['ok' => false, 'message' => 'Não foi possível concluir o resgate. Tente novamente.'];
    }
}
