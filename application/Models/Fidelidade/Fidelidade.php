<?php

namespace Agencia\Close\Models\Fidelidade;

use Agencia\Close\Conn\Read;
use Agencia\Close\Models\Model;

class Fidelidade extends Model
{
    /**
     * Clientes (tipo 4) com saldo, total ganho e total gasto — apenas quem já ganhou ou já gastou pontos.
     */
    public function getUsuariosComPontos(): Read
    {
        $read = new Read();
        $read->FullRead(
            "SELECT u.id, u.nome, u.email, u.telefone, u.cpf, u.data,
                COALESCE(SUM(m.pontos), 0) AS saldo,
                COALESCE(SUM(CASE WHEN m.pontos > 0 THEN m.pontos ELSE 0 END), 0) AS total_ganho,
                COALESCE(SUM(CASE WHEN m.pontos < 0 THEN -m.pontos ELSE 0 END), 0) AS total_gasto
            FROM usuarios u
            INNER JOIN fidelidade_movimentacao m ON m.id_usuario = u.id
            WHERE u.tipo = '4'
            GROUP BY u.id, u.nome, u.email, u.telefone, u.cpf, u.data
            HAVING total_ganho > 0 OR total_gasto > 0
            ORDER BY saldo DESC, u.nome ASC"
        );
        return $read;
    }

    public function getClientePorId(int $id): Read
    {
        $read = new Read();
        $read->FullRead(
            "SELECT * FROM usuarios WHERE id = :id AND tipo = '4' LIMIT 1",
            "id={$id}"
        );
        return $read;
    }

    public function getMovimentacoesPorUsuario(int $idUsuario, int $limite = 500): Read
    {
        $read = new Read();
        $read->FullRead(
            "SELECT * FROM fidelidade_movimentacao
             WHERE id_usuario = :id_usuario
             ORDER BY criado_em DESC, id DESC
             LIMIT :limite",
            "id_usuario={$idUsuario}&limite={$limite}"
        );
        return $read;
    }

    public function getResgatesPorUsuario(int $idUsuario): Read
    {
        $read = new Read();
        $read->FullRead(
            "SELECT r.*, s.nome AS suite_nome
             FROM fidelidade_resgate r
             LEFT JOIN suites s ON s.id = r.id_suite
             WHERE r.id_usuario = :id_usuario
             ORDER BY r.criado_em DESC",
            "id_usuario={$idUsuario}"
        );
        return $read;
    }

    public function getResumoPontosUsuario(int $idUsuario): Read
    {
        $read = new Read();
        $read->FullRead(
            "SELECT 
                COALESCE(SUM(pontos), 0) AS saldo,
                COALESCE(SUM(CASE WHEN pontos > 0 THEN pontos ELSE 0 END), 0) AS total_ganho,
                COALESCE(SUM(CASE WHEN pontos < 0 THEN -pontos ELSE 0 END), 0) AS total_gasto
             FROM fidelidade_movimentacao
             WHERE id_usuario = :id_usuario",
            "id_usuario={$idUsuario}"
        );
        return $read;
    }
}
