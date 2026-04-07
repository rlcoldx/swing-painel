<?php
    include('../config/config.php');

    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
    header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");

    $_POST = json_decode(file_get_contents("php://input"), true);

    if(@$_POST['token'] == TOKEN){

		$cupomCodigo = isset($_POST['cupom']) ? trim((string) $_POST['cupom']) : '';

		/**
		 * Cupom interno FIDELIDADE (pontos de fidelidade):
		 * — Não consulta a tabela `cupons` (ignora status_cupom, data_expiracao e limite_uso).
		 * — Só valida token, saldo de pontos e valor do período.
		 */
		if (strtoupper($cupomCodigo) === 'FIDELIDADE') {

			$idUsuario = isset($_POST['id_usuario']) ? (int) $_POST['id_usuario'] : 0;
			$valorPeriodo = isset($_POST['valor_periodo']) ? (float) str_replace(',', '.', preg_replace('/[^\d.,-]/', '', (string) $_POST['valor_periodo'])) : 0;

			if ($idUsuario <= 0 || $valorPeriodo <= 0) {
				$result = array("result" => "error", "message" => "Para usar pontos informe id_usuario e valor_periodo.");
				echo json_encode($result);
				exit;
			}

			// Lógica inline: não depende de config/fidelidade.php (evita fatal se o servidor
			// carregou include_once uma versão antiga do arquivo sem essas funções).
			try {
				$db->query('SELECT 1 FROM fidelidade_movimentacao LIMIT 1');
			} catch (Throwable $e) {
				$result = array("result" => "error", "message" => "Programa de fidelidade indisponível.");
				echo json_encode($result);
				exit;
			}

			$valorPorPonto = 0.25;
			$pontosNecessarios = (int) ceil($valorPeriodo / $valorPorPonto - 1e-9);
			$stSaldo = $db->prepare('SELECT COALESCE(SUM(pontos), 0) AS s FROM fidelidade_movimentacao WHERE id_usuario = ?');
			$stSaldo->execute(array($idUsuario));
			$saldo = (int) $stSaldo->fetchColumn();

			if ($saldo < $pontosNecessarios) {
				$result = array("result" => "error", "message" => "Saldo de pontos insuficiente para este valor.");
				echo json_encode($result);
				exit;
			}

			$response = array(
				"result" => "success",
				"valor_reserva" => "0.00",
				"tipo_desconto" => "porcentagem",
				"valor_desconto" => "100",
				"pontos_utilizados" => $pontosNecessarios,
				"ocultar_campo_cupom" => true,
			);
			echo json_encode($response);
			exit;
		}

		$dados = array($_POST['cupom']);
		/** Demais cupons: tabela `cupons` com validade e limite. Código FIDELIDADE nunca passa aqui (tratado acima). */
		$sql_cupom = $db->prepare(
			"SELECT * FROM cupons WHERE `codigo` = ?
			 AND UPPER(TRIM(`codigo`)) <> 'FIDELIDADE'
			 AND (`data_expiracao` IS NULL OR `data_expiracao` >= CURDATE())
			 AND status_cupom = 'yes'"
		);
		$sql_cupom->execute($dados);
		$cupom = $sql_cupom->fetch(PDO::FETCH_ASSOC);

	    $json = "";

	    if ($cupom && $cupom['id'] != '' && (int) $cupom['quantidade_usos'] <= (int) $cupom['limite_uso']) {

			if ($cupom['tipo_desconto'] == 'porcentagem') {
				$valor_aplicado = $_POST['valor_periodo'] - ($_POST['valor_periodo'] * ($cupom['valor_desconto'] / 100));
			    $valor_desconto = number_format($cupom['valor_desconto'], 0, '.', '');
			}

			if ($cupom['tipo_desconto'] == 'valor_fixo') {
				$valor_aplicado = $_POST['valor_periodo'] - $cupom['valor_desconto'];
			    $valor_desconto = $cupom['valor_desconto'];
			}

			$valor_formatado = number_format($valor_aplicado, 2, '.', '');
			$response = array(
			    "tipo_desconto" => $cupom['tipo_desconto'],
			    "valor_desconto" => $valor_desconto,
			    "valor_reserva" => $valor_formatado,
			    "result" => "success"
			);

			echo json_encode($response);

	    }else{
	        
	        $result = array("result" => "error");
	        $json = json_encode($result);
	        echo $json;

	    }

	}else{
		echo 'Sem autorização';
	}
