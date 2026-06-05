<?php

function sis_headers(): array
{
    return [
        'token: ' . SIS_KEY,
        'softhouse: ' . SOFTHOUSE,
    ];
}

function converterHoraPara24h(string $hora): string
{
    $hora = trim($hora);
    if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $hora, $m)) {
        return sprintf('%02d:%02d:00', (int) $m[1], (int) $m[2]);
    }
    if (preg_match('/^(\d{1,2})h(\d{2})?$/i', $hora, $m)) {
        $min = isset($m[2]) ? (int) $m[2] : 0;
        return sprintf('%02d:%02d:00', (int) $m[1], $min);
    }
    return '22:00:00';
}

function converterParaMinutos(string $periodo): int
{
    if (strcasecmp($periodo, 'Pernoite') === 0) {
        return 720;
    }
    if (preg_match('/^(\d{1,2}):(\d{2})$/', $periodo, $m)) {
        return ((int) $m[1] * 60) + (int) $m[2];
    }
    return 120;
}

function sis_montar_date_scheduled(string $dataReserva, string $chegadaReserva, string $periodoReserva): string
{
    $hora = converterHoraPara24h($chegadaReserva);
    $data = date('Y-m-d', strtotime($dataReserva));
    if (strcasecmp($periodoReserva, 'Pernoite') === 0) {
        $h = (int) substr($hora, 0, 2);
        if ($h >= 0 && $h <= 4) {
            $data = date('Y-m-d', strtotime($data . ' +1 day'));
        }
    }
    return $data . ' ' . $hora;
}

function sis_extrair_mensagem_erro(array $sisResponse): string
{
    if (!empty($sisResponse['message']) && is_string($sisResponse['message'])) {
        return $sisResponse['message'];
    }
    if (!empty($sisResponse['message']) && is_array($sisResponse['message'])) {
        $partes = [];
        foreach ($sisResponse['message'] as $campo => $msgs) {
            if (is_array($msgs)) {
                $partes[] = $campo . ': ' . implode(', ', $msgs);
            } else {
                $partes[] = (string) $msgs;
            }
        }
        if ($partes) {
            return implode(' | ', $partes);
        }
    }
    if (!empty($sisResponse['errors']) && is_array($sisResponse['errors'])) {
        return implode(' | ', array_map('strval', $sisResponse['errors']));
    }
    return 'Erro ao criar reserva no SIS';
}

function getStatusSis(int $situation): string
{
    if ($situation === 1) {
        return 'Pendente';
    }
    if ($situation === 3) {
        return 'Aceito';
    }
    if (in_array($situation, [4, 6, 10, 11, 12, 15], true)) {
        return 'Aceito';
    }
    if (in_array($situation, [2, 5], true)) {
        return 'Recusado';
    }
    if (in_array($situation, [7, 8, 9, 13, 14, 98, 99], true)) {
        return 'Cancelado';
    }
    return 'Pendente';
}

function sis_extrair_situation(array $sisData, int $fallback = 0): int
{
    if (isset($sisData['result']['reservation']['situation'])) {
        return (int) $sisData['result']['reservation']['situation'];
    }
    if (isset($sisData['result']['situation'])) {
        return (int) $sisData['result']['situation'];
    }
    if (!empty($sisData['result']['logs']) && is_array($sisData['result']['logs'])) {
        $logs = $sisData['result']['logs'];
        $ultimo = end($logs);
        if (isset($ultimo['situation'])) {
            return (int) $ultimo['situation'];
        }
    }
    return $fallback;
}

function sis_situacao_e_cancelada(int $situation): bool
{
    return getStatusSis($situation) === 'Cancelado';
}

function sis_cancelar_reserva($idReservaSis): void
{
    if (!defined('SIS_ATIVO') || !SIS_ATIVO) {
        return;
    }
    $id = (int) $idReservaSis;
    if ($id <= 0) {
        return;
    }

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => SIS_API . '/api/reservation/' . $id,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'DELETE',
        CURLOPT_HTTPHEADER => sis_headers(),
    ]);
    curl_exec($curl);
    curl_close($curl);
}

function sis_get_reservation(int $idReservaSis): array
{
    if (!defined('SIS_ATIVO') || !SIS_ATIVO || $idReservaSis <= 0) {
        return [];
    }

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => SIS_API . '/api/reservation/' . $idReservaSis,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => sis_headers(),
    ]);
    $response = curl_exec($curl);
    curl_close($curl);

    return json_decode((string) $response, true) ?: [];
}

function sis_confirmar_reserva(int $idReservaSis): void
{
    if (!defined('SIS_ATIVO') || !SIS_ATIVO || $idReservaSis <= 0) {
        return;
    }

    sleep(5);

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => SIS_API . '/api/reservation/' . $idReservaSis,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_HTTPHEADER => array_merge(sis_headers(), ['Content-Type: application/json']),
    ]);
    curl_exec($curl);
    curl_close($curl);
}

function sis_criar_reserva(array $payload): array
{
    if (!defined('SIS_ATIVO') || !SIS_ATIVO) {
        return ['success' => false, 'message' => 'Integração SIS desativada'];
    }

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => SIS_API . '/api/reservation',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => array_merge(sis_headers(), ['Content-Type: application/json']),
    ]);
    $response = curl_exec($curl);
    curl_close($curl);

    return json_decode((string) $response, true) ?: ['success' => false, 'message' => 'Resposta inválida do SIS'];
}
