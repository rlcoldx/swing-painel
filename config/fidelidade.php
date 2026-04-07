<?php
/**
 * Fidelidade — regras e acesso ao banco ($db PDO vindo de config.php).
 *
 * Crédito: pagamento aprovado (Mercado Pago ou fluxo já tratado no app).
 * Débito: reserva paga com pontos (preference fidelidade + coluna reservas.pontos_fidelidade).
 */

if (!defined('FIDELIDADE_PONTOS_POR_REAL')) {
    define('FIDELIDADE_PONTOS_POR_REAL', 1);
}
if (!defined('FIDELIDADE_VALOR_POR_PONTO_RESGATE')) {
    define('FIDELIDADE_VALOR_POR_PONTO_RESGATE', 0.25);
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
const FIDELIDADE_TIPO_DEBITO_RESERVA = 'debito_fidelidade_reserva';
const FIDELIDADE_TIPO_RESGATE_SUITE = 'debito_resgate_suite';
const FIDELIDADE_TIPO_RESGATE_ALIMENTO = 'debito_resgate_alimento';
const FIDELIDADE_TIPO_RESGATE_BEBIDA = 'debito_resgate_bebida';
const FIDELIDADE_TIPO_AJUSTE_ADMIN = 'ajuste_admin';

/**
 * Tabelas de fidelidade existem e são legíveis.
 * Só memoriza sucesso; se a primeira tentativa falhar, tenta de novo depois (útil após criar tabelas).
 */
function fidelidade_tabelas_existem(PDO $db): bool
{
    static $confirmado = false;
    if ($confirmado) {
        return true;
    }
    try {
        $db->query('SELECT 1 FROM fidelidade_movimentacao LIMIT 1');
        $confirmado = true;
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function fidelidade_saldo_usuario(PDO $db, int $idUsuario): int
{
    if (!fidelidade_tabelas_existem($db) || $idUsuario <= 0) {
        return 0;
    }
    $st = $db->prepare('SELECT COALESCE(SUM(pontos), 0) AS s FROM fidelidade_movimentacao WHERE id_usuario = ?');
    $st->execute([$idUsuario]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    return (int) ($row['s'] ?? 0);
}

/**
 * Credita pontos após pagamento aprovado (R$ 1,00 → 1 ponto, parte inteira).
 * Idempotente: uma linha por reserva + tipo credito_reserva_app.
 */
function fidelidade_creditar_por_pagamento_aprovado(PDO $db, int $idReserva, float $valorPagoReal): bool
{
    if (!fidelidade_tabelas_existem($db) || $idReserva <= 0) {
        return false;
    }

    $pontos = (int) floor(max(0.0, $valorPagoReal) * FIDELIDADE_PONTOS_POR_REAL);
    if ($pontos <= 0) {
        return false;
    }

    $st = $db->prepare('SELECT id_usuario, codigo_reserva FROM reservas WHERE id = ? LIMIT 1');
    $st->execute([$idReserva]);
    $reserva = $st->fetch(PDO::FETCH_ASSOC);
    if (!$reserva || empty($reserva['id_usuario'])) {
        return false;
    }
    $idUsuario = (int) $reserva['id_usuario'];

    $dup = $db->prepare(
        'SELECT id FROM fidelidade_movimentacao WHERE id_reserva = ? AND tipo = ? LIMIT 1'
    );
    $dup->execute([$idReserva, FIDELIDADE_TIPO_CREDITO_RESERVA]);
    if ($dup->fetch()) {
        return true;
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

function fidelidade_pontos_necessarios_para_valor(float $valorReais): int
{
    if ($valorReais <= 0) {
        return 0;
    }

    return (int) ceil($valorReais / FIDELIDADE_VALOR_POR_PONTO_RESGATE - 1e-9);
}

function fidelidade_reserva_tem_debito_fidelidade(PDO $db, int $idReserva): bool
{
    if (!fidelidade_tabelas_existem($db) || $idReserva <= 0) {
        return false;
    }
    $st = $db->prepare(
        'SELECT 1 FROM fidelidade_movimentacao WHERE id_reserva = ? AND tipo = ? LIMIT 1'
    );
    $st->execute([$idReserva, FIDELIDADE_TIPO_DEBITO_RESERVA]);

    return (bool) $st->fetchColumn();
}

/**
 * Debita pontos pela quantidade gravada em reservas.pontos_fidelidade (idempotente por reserva).
 */
function fidelidade_debitar_reserva_pontos_quantidade(PDO $db, int $idUsuario, int $idReserva, int $pontos): bool
{
    if (!fidelidade_tabelas_existem($db) || $idReserva <= 0 || $idUsuario <= 0 || $pontos <= 0) {
        return false;
    }

    if (fidelidade_reserva_tem_debito_fidelidade($db, $idReserva)) {
        return false;
    }

    if (fidelidade_saldo_usuario($db, $idUsuario) < $pontos) {
        return false;
    }

    $stSuite = $db->prepare('SELECT id_suite FROM reservas WHERE id = ? AND id_usuario = ? LIMIT 1');
    $stSuite->execute([$idReserva, $idUsuario]);
    $rowRes = $stSuite->fetch(PDO::FETCH_ASSOC);
    if (!$rowRes) {
        return false;
    }
    $idSuite = (int) ($rowRes['id_suite'] ?? 0);

    $desc = sprintf('Reserva paga com pontos (%d pontos)', $pontos);

    try {
        $insResgate = $db->prepare(
            'INSERT INTO fidelidade_resgate (id_usuario, tipo, pontos, id_suite, status, observacao)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $obs = sprintf('Reserva #%d — pagamento com pontos de fidelidade.', $idReserva);
        $insResgate->execute([
            $idUsuario,
            'suite',
            $pontos,
            $idSuite > 0 ? $idSuite : null,
            'atendido',
            $obs,
        ]);
        $idResgate = (int) $db->lastInsertId();

        $ins = $db->prepare(
            'INSERT INTO fidelidade_movimentacao (id_usuario, pontos, tipo, descricao, id_reserva, id_resgate)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $ins->execute([
            $idUsuario,
            -$pontos,
            FIDELIDADE_TIPO_DEBITO_RESERVA,
            $desc,
            $idReserva,
            $idResgate > 0 ? $idResgate : null,
        ]);
    } catch (Throwable $e) {
        return false;
    }

    return true;
}

function fidelidade_debitar_reserva_pontos(PDO $db, int $idUsuario, int $idReserva, float $valorOriginalReais): bool
{
    $pontos = fidelidade_pontos_necessarios_para_valor($valorOriginalReais);

    return fidelidade_debitar_reserva_pontos_quantidade($db, $idUsuario, $idReserva, $pontos);
}

/**
 * Preference com pontos: debita (se preciso), grava pagamento approved. Idempotente.
 *
 * @return array{ok:bool, message?:string, idempotente?:bool}
 */
function fidelidade_processar_preference_fidelidade(PDO $db, array $reserva, int $idUsuario): array
{
    $pontosDebito = (int) ($reserva['pontos_fidelidade'] ?? 0);
    if ($pontosDebito <= 0) {
        return ['ok' => false, 'message' => 'Reserva sem pontos de fidelidade.'];
    }

    $idReserva = (int) ($reserva['id'] ?? 0);
    $codigoRef = trim((string) ($reserva['codigo_reserva'] ?? ''));

    try {
        $db->beginTransaction();

        $stPag = $db->prepare(
            'SELECT id, pagamento_status FROM pagamentos WHERE id_reserva = ? ORDER BY id DESC LIMIT 1'
        );
        $stPag->execute([$idReserva]);
        $rowPag = $stPag->fetch(PDO::FETCH_ASSOC);

        if (!$rowPag && $codigoRef !== '') {
            $st2 = $db->prepare(
                'SELECT id, pagamento_status FROM pagamentos WHERE external_reference = ? ORDER BY id DESC LIMIT 1'
            );
            $st2->execute([$codigoRef]);
            $rowPag = $st2->fetch(PDO::FETCH_ASSOC);
        }

        if ($rowPag && strtolower((string) ($rowPag['pagamento_status'] ?? '')) === 'approved') {
            $db->commit();
            return ['ok' => true, 'idempotente' => true];
        }

        if (!fidelidade_reserva_tem_debito_fidelidade($db, $idReserva)) {
            $okDebito = fidelidade_debitar_reserva_pontos_quantidade($db, $idUsuario, $idReserva, $pontosDebito);
            if (!$okDebito && !fidelidade_reserva_tem_debito_fidelidade($db, $idReserva)) {
                $db->rollBack();
                return ['ok' => false, 'message' => 'Não foi possível debitar os pontos.'];
            }
        }

        $pid = (string) (900000000 + $idReserva);

        if ($rowPag) {
            $up = $db->prepare(
                'UPDATE pagamentos SET id_user = ?, id_reserva = ?, pagamento_id = ?, pagamento_metodo = ?, pagamento_valor = ?, pagamento_parcelas = ?, pagamento_status = ?, external_reference = ? WHERE id = ?'
            );
            $up->execute([
                $idUsuario,
                $idReserva,
                $pid,
                'fidelidade',
                '0.00',
                '1',
                'approved',
                $codigoRef !== '' ? $codigoRef : null,
                (int) $rowPag['id'],
            ]);
        } else {
            $ins = $db->prepare(
                'INSERT INTO pagamentos (id_user, id_reserva, pagamento_id, pagamento_metodo, pagamento_valor, pagamento_parcelas, pagamento_status, external_reference)
                 VALUES (?,?,?,?,?,?,?,?)'
            );
            $ins->execute([
                $idUsuario,
                $idReserva,
                $pid,
                'fidelidade',
                '0.00',
                '1',
                'approved',
                $codigoRef !== '' ? $codigoRef : null,
            ]);
        }

        $db->commit();
        return ['ok' => true];
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        return ['ok' => false, 'message' => 'Erro ao registrar pagamento com pontos.'];
    }
}

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
         FROM fidelidade_movimentacao WHERE id_usuario = ?'
    );
    $st->execute([$idUsuario]);
    $row = $st->fetch(PDO::FETCH_ASSOC) ?: ['saldo' => 0, 'total_ganho' => 0, 'total_gasto' => 0];

    return [
        'saldo' => (int) $row['saldo'],
        'total_ganho' => (int) $row['total_ganho'],
        'total_gasto' => (int) $row['total_gasto'],
    ];
}

function fidelidade_regras_publicas(): array
{
    return [
        'pontos_por_real' => FIDELIDADE_PONTOS_POR_REAL,
        'resgate_suite_pontos' => FIDELIDADE_RESGATE_SUITE_PONTOS,
        'resgate_alimento_pontos' => FIDELIDADE_RESGATE_ALIMENTO_PONTOS,
        'resgate_bebida_pontos' => FIDELIDADE_RESGATE_BEBIDA_PONTOS,
        'valor_ponto_reais' => FIDELIDADE_VALOR_POR_PONTO_RESGATE,
        'pontos_por_real_em_resgate' => (int) round(1 / FIDELIDADE_VALOR_POR_PONTO_RESGATE),
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

function fidelidade_usuario_tem_reserva_na_suite(PDO $db, int $idUsuario, int $idSuite): bool
{
    if (!fidelidade_tabelas_existem($db)) {
        return false;
    }
    $st = $db->prepare('SELECT id FROM reservas WHERE id_usuario = ? AND id_suite = ? LIMIT 1');
    $st->execute([$idUsuario, $idSuite]);

    return (bool) $st->fetch();
}

/** @deprecated Fluxo antigo de resgate no app. */
function fidelidade_processar_resgate_app(PDO $db, int $idUsuario, string $tipo, ?int $idSuite = null): array
{
    return [
        'ok' => false,
        'message' => 'Use pagamento com pontos na reserva (cupom FIDELIDADE).',
    ];
}
