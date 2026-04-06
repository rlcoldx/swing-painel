<?php

namespace Agencia\Close\Controllers\Fidelidade;

use Agencia\Close\Controllers\Controller;
use Agencia\Close\Models\Fidelidade\Fidelidade;

class FidelidadeController extends Controller
{
    public function index($params)
    {
        $this->setParams($params);

        $model = new Fidelidade();
        $usuarios = $model->getUsuariosComPontos()->getResult();
        if ($usuarios === null) {
            $usuarios = [];
        }

        $this->render('pages/fidelidade/index.twig', [
            'titulo' => 'Fidelidade — Clientes e pontos',
            'usuarios' => $usuarios,
        ]);
    }

    public function usuario($params)
    {
        $this->setParams($params);
        $id = isset($params['id']) ? (int) $params['id'] : 0;

        $model = new Fidelidade();
        $clienteRead = $model->getClientePorId($id);
        $cliente = $clienteRead->getResult();
        if (!$cliente || !isset($cliente[0])) {
            header('HTTP/1.0 404 Not Found');
            echo 'Cliente não encontrado.';
            return;
        }
        $cliente = $cliente[0];

        $movRead = $model->getMovimentacoesPorUsuario($id);
        $movimentacoes = $movRead->getResult();
        if ($movimentacoes === null) {
            $movimentacoes = [];
        }

        $resRead = $model->getResgatesPorUsuario($id);
        $resgates = $resRead->getResult();
        if ($resgates === null) {
            $resgates = [];
        }

        $resumoRead = $model->getResumoPontosUsuario($id);
        $resumoRow = $resumoRead->getResult();
        $resumo = [
            'saldo' => isset($resumoRow[0]) ? (int) $resumoRow[0]['saldo'] : 0,
            'total_ganho' => isset($resumoRow[0]) ? (int) $resumoRow[0]['total_ganho'] : 0,
            'total_gasto' => isset($resumoRow[0]) ? (int) $resumoRow[0]['total_gasto'] : 0,
        ];

        $this->render('pages/fidelidade/usuario.twig', [
            'titulo' => 'Fidelidade — ' . ($cliente['nome'] ?? 'Cliente'),
            'cliente' => $cliente,
            'movimentacoes' => $movimentacoes,
            'resgates' => $resgates,
            'resumo' => $resumo,
        ]);
    }
}
