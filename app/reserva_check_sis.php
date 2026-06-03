<?php

function reserva_check_sis_sync(PDO $db, array &$check): void
{
	$idReservaSis = (int) ($check['id_reserva_sis'] ?? 0);

	if ($idReservaSis <= 0) {
		return;
	}

	$sisData = sis_get_reservation($idReservaSis);
	$situation = (int) ($sisData['result']['reservation']['situation'] ?? ($check['status_sis'] ?? 0));

	if ($situation === 3) {
		sis_confirmar_reserva($idReservaSis);
		$upd = $db->prepare('UPDATE reservas SET status_sis = 4, status_reserva = ? WHERE id = ?');
		$upd->execute(['Aceito', $check['id']]);
		$check['status_reserva'] = 'Aceito';
		$check['status_sis'] = 4;
	} else {
		$statusReserva = getStatusSis($situation);
		$upd = $db->prepare('UPDATE reservas SET status_sis = ?, status_reserva = ? WHERE id = ?');
		$upd->execute([$situation, $statusReserva, $check['id']]);
		$check['status_reserva'] = $statusReserva;
		$check['status_sis'] = $situation;
	}
}
