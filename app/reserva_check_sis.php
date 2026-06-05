<?php

if (
	!defined('RESERVA_CHECK_BOOTSTRAP')
	&& basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'reserva_check_sis.php'
) {
	require __DIR__ . '/reserva_check.php';
	return;
}

if (!function_exists('sis_extrair_situation')) {
	if (!defined('SIS_API')) {
		require_once __DIR__ . '/../config/config.php';
	} else {
		require_once __DIR__ . '/../config/sis.php';
	}
}

function reserva_check_sis_sync(PDO $db, array &$check): void
{
	$idReservaSis = (int) ($check['id_reserva_sis'] ?? 0);

	if ($idReservaSis <= 0) {
		return;
	}

	$sisData = sis_get_reservation($idReservaSis);
	$situation = sis_extrair_situation($sisData, (int) ($check['status_sis'] ?? 0));

	if ($situation === 3) {
		sis_confirmar_reserva($idReservaSis);
		$upd = $db->prepare('UPDATE reservas SET status_sis = 4, status_reserva = ? WHERE id = ?');
		$upd->execute(['Aceito', $check['id']]);
		$check['status_reserva'] = 'Aceito';
		$check['status_sis'] = 4;
		return;
	}

	$statusReserva = getStatusSis($situation);

	if (
		in_array($statusReserva, ['Cancelado', 'Recusado'], true)
		&& reserva_tem_pagamento_aprovado($db, (int) $check['id'])
	) {
		return;
	}

	$upd = $db->prepare('UPDATE reservas SET status_sis = ?, status_reserva = ? WHERE id = ?');
	$upd->execute([$situation, $statusReserva, $check['id']]);
	$check['status_reserva'] = $statusReserva;
	$check['status_sis'] = $situation;
}
