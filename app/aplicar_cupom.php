<?php
    include('../config/config.php');

    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
    header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");

    $_POST = json_decode(file_get_contents("php://input"), true);

    if(@$_POST['token'] == TOKEN){

		$dados = array($_POST['cupom']);
		$sql_cupom = $db->prepare("SELECT * FROM cupons WHERE `codigo` = ? AND (`data_expiracao` IS NULL OR `data_expiracao` >= CURDATE()) AND status_cupom = 'yes'");
		$sql_cupom->execute($dados);
		$cupom = $sql_cupom->fetch(PDO::FETCH_ASSOC);

	    $json = "";

	    if($cupom['id'] != '' && $cupom['quantidade_usos'] <= $cupom['limite_uso']){

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