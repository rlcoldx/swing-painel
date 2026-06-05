<?php

namespace Agencia\Close\Models\Reserva;

use Agencia\Close\Conn\Conn;
use Agencia\Close\Conn\Read;
use Agencia\Close\Conn\Create;
use Agencia\Close\Conn\Update;
use Agencia\Close\Models\Model;

class Reserva extends Model 
{

    public function checkReservas(): Read
    {
        $this->read = new Read();
        $this->read->FullRead("SELECT * FROM reservas WHERE status_reserva = 'Pendente' ORDER BY id DESC");
        return $this->read;
    }

    public function getReservas($limit = 99999, array $filtros = []): Read
    {
        $read = new Read();

        $where = [];
        $params = [];

        $dataDe = isset($filtros['data_de']) ? trim((string) $filtros['data_de']) : '';
        $dataAte = isset($filtros['data_ate']) ? trim((string) $filtros['data_ate']) : '';
        $payStatus = isset($filtros['pagamento_status']) ? trim((string) $filtros['pagamento_status']) : '';
        $statusReserva = isset($filtros['status_reserva']) ? trim((string) $filtros['status_reserva']) : '';

        if ($dataDe !== '') {
            $where[] = 'r.data_reserva >= :data_de';
            $params['data_de'] = $dataDe;
        }
        if ($dataAte !== '') {
            $where[] = 'r.data_reserva <= :data_ate';
            $params['data_ate'] = $dataAte;
        }
        if ($statusReserva !== '') {
            $where[] = 'r.status_reserva = :status_reserva';
            $params['status_reserva'] = $statusReserva;
        }
        if ($payStatus !== '') {
            if ($payStatus === 'nao_iniciado') {
                $where[] = 'p.pagamento_status IS NULL';
            } else {
                $where[] = 'p.pagamento_status = :pagamento_status';
                $params['pagamento_status'] = $payStatus;
            }
        }

        $sqlWhere = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $limit = (int) $limit;
        $params['limit'] = $limit;

        $parse = http_build_query($params);

        $read->FullRead(
            "SELECT 
                r.*,
                s.nome AS suite_nome,
                p.pagamento_status,
                p.pagamento_metodo,
                p.pagamento_valor
             FROM reservas AS r
             INNER JOIN suites AS s ON s.id = r.id_suite
             LEFT JOIN (
                SELECT p1.*
                FROM pagamentos p1
                INNER JOIN (
                    SELECT id_reserva, MAX(id) AS id
                    FROM pagamentos
                    GROUP BY id_reserva
                ) x ON x.id = p1.id
             ) p ON p.id_reserva = r.id
             {$sqlWhere}
             ORDER BY r.id DESC
             LIMIT :limit",
            $parse
        );
        return $read;
    }

    public function statusReserva($id_reserva): Read
    {
        $read = new Read();
        $read->FullRead("SELECT r.*, s.nome AS suite_nome, p.pagamento_status, p.pagamento_metodo, p.pagamento_valor, p.external_reference FROM reservas AS r
        INNER JOIN suites AS s ON s.id = r.id_suite
        LEFT JOIN pagamentos AS p ON p.id_reserva = r.id
        WHERE r.id = :id_reserva
        ORDER BY r.id DESC", "id_reserva={$id_reserva}");
        return $read;
    }
    
    public function statusReservaSave($params): Update
    {
        $id = $params['id'];
        unset($params['id']);
        $update = new Update();
        $update->ExeUpdate('reservas', $params, 'WHERE `id` = :id', "id={$id}");
        return $update;
    }

    public function checkReservasExpiradas(): Read
    {
        $read = new Read();
        $read->FullRead(
            "SELECT r.*
             FROM reservas AS r
             WHERE r.status_reserva = 'Pendente'
               AND r.date_create < DATE_SUB(NOW(), INTERVAL 30 MINUTE)
               AND NOT EXISTS (
                   SELECT 1
                   FROM pagamentos p
                   WHERE p.id_reserva = r.id
                     AND p.pagamento_status = 'approved'
               )
             ORDER BY r.id ASC"
        );
        return $read;
    }

    public function reservaPodeSerCanceladaPorExpiracao(int $idReserva): bool
    {
        $read = new Read();
        $read->FullRead(
            "SELECT r.status_reserva, r.date_create,
                    (SELECT COUNT(*) FROM pagamentos p
                     WHERE p.id_reserva = r.id AND p.pagamento_status = 'approved') AS pagamento_aprovado
             FROM reservas AS r
             WHERE r.id = :id
             LIMIT 1",
            "id={$idReserva}"
        );
        $row = $read->getResult()[0] ?? null;
        if (!$row) {
            return false;
        }
        if (($row['status_reserva'] ?? '') !== 'Pendente') {
            return false;
        }
        if ((int) ($row['pagamento_aprovado'] ?? 0) > 0) {
            return false;
        }
        if (empty($row['date_create'])) {
            return false;
        }
        return strtotime($row['date_create'] . ' +30 minutes') < time();
    }

    public function cancelarReservaExpirada(array $reserva): void
    {
        $idReserva = (int) ($reserva['id'] ?? 0);
        if ($idReserva <= 0 || !$this->reservaPodeSerCanceladaPorExpiracao($idReserva)) {
            return;
        }

        $update = new Update();
        $update->ExeUpdate(
            'reservas',
            ['status_reserva' => 'Cancelado'],
            'WHERE `id` = :id AND `status_reserva` = :status',
            'id=' . $idReserva . '&status=Pendente'
        );

        $idReservaSis = (int) ($reserva['id_reserva_sis'] ?? 0);
        if (defined('SIS_ATIVO') && SIS_ATIVO && $idReservaSis > 0) {
            $sisData = sis_get_reservation($idReservaSis);
            $situation = sis_extrair_situation($sisData, (int) ($reserva['status_sis'] ?? 0));
            if (!sis_situacao_e_paga_ou_confirmada($situation)) {
                sis_cancelar_reserva($idReservaSis);
                $updateSis = new Update();
                $updateSis->ExeUpdate(
                    'reservas',
                    ['status_sis' => 8],
                    'WHERE `id` = :id',
                    'id=' . $idReserva
                );
            }
        }
    }
    
    
}