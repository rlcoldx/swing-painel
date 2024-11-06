<?php 
	include('../config/config.php');

    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
    header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");

    $_POST = json_decode(file_get_contents("php://input"), true);

    $dados = array($_GET['codigo_reserva']);
	$sql_reserva = $db->prepare("SELECT r.*, s.nome as suite_nome FROM reservas AS r
								INNER JOIN suites AS s ON s.id = r.id_suite
								WHERE r.codigo_reserva = ?");
	$sql_reserva->execute($dados);
	$reserva = $sql_reserva->fetch(PDO::FETCH_ASSOC);

    if(@$_POST['payment_method_id'] == 'pix') {

    	$curl = curl_init();
		curl_setopt_array($curl, array(
			CURLOPT_URL => 'https://api.mercadopago.com/v1/payments',
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 0,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => 'POST',
			CURLOPT_POSTFIELDS =>'{
					"transaction_amount": '.$_POST['transaction_amount'].',
					"payment_method_id": "'.$_POST['payment_method_id'].'",
					"payer": {
						"email": "'.$_POST['payer']['email'].'"
					}
				}',
				CURLOPT_HTTPHEADER => array(
					'Content-Type: application/json',
					'X-Idempotency-Key: vianna-reserva-'.$reserva['id'],
					'Authorization: Bearer ' . ACCESSTOKEN
				),
			));

		$response = curl_exec($curl);
		curl_close($curl);

		echo $response;

    }

    if(isset($_POST['token'])) {

		$curl = curl_init();
		curl_setopt_array($curl, array(
			CURLOPT_URL => 'https://api.mercadopago.com/v1/payments',
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 0,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => 'POST',
			CURLOPT_POSTFIELDS =>'{
				"description": "Pagamento de Reserva",
				"installments": '.$_POST['installments'].',
				"payer": {
					"email": "'.$_POST['payer']['email'].'",
					"identification": {
						"type": "'.$_POST['payer']['identification']['type'].'",
						"number": "'.$_POST['payer']['identification']['number'].'"
					}
					},
					"issuer_id": "'.$_POST['issuer_id'].'",
					"payment_method_id": "'.$_POST['payment_method_id'].'",
					"token": "'.$_POST['token'].'",
					"transaction_amount": '.$_POST['transaction_amount'].'
				}',
				CURLOPT_HTTPHEADER => array(
					'Content-Type: application/json',
					'X-Idempotency-Key: vianna-reserva-'.$reserva['id'],
					'Authorization: Bearer ' . ACCESSTOKEN
				),
			));

		$response = curl_exec($curl);
		curl_close($curl);

		echo $response;

	}

	//TRANSFORMA O RESPONSE PARA SALVAR NO BANCO
	$response = json_decode($response);

	if($response->id) {

		if(!empty($_POST['installments'])){
			$pagamento_parcelas = $_POST['installments'];
		}else{
			$pagamento_parcelas = 1;
		}

		//VERIFICA SE EXISTE O PAGAMENTO
		$sql_check = $db->prepare("SELECT * FROM `pagamentos` WHERE `id_user` = '".$reserva['id_usuario']."' AND `external_reference` = '".$reserva['codigo_reserva']."'");
		$sql_check->execute();
		

		if($sql_check->rowCount() == 0){
			//INSERE SE NÃO EXISTIR
			$dados_pagamento = array($reserva['id_usuario'], $reserva['id'], $response->id, $_POST['payment_method_id'], number_format($_POST['transaction_amount'], 2, '.', ''), $pagamento_parcelas, $response->status, $reserva['codigo_reserva']);
			$sql_pagamento = $db->prepare("INSERT INTO `pagamentos` (`id_user`, `id_reserva`, `pagamento_id`, `pagamento_metodo`, `pagamento_valor`, `pagamento_parcelas`, pagamento_status, `external_reference`) VALUES (?,?,?,?,?,?,?,?)");
			$sql_pagamento->execute($dados_pagamento);
		
		}else{
			//ATUALIZA SE NÃO EXISTIR
			$dados_pagamento = array($reserva['id_usuario'], $reserva['id'], $response->id, $_POST['payment_method_id'], number_format($_POST['transaction_amount'], 2, '.', ''), $pagamento_parcelas, $response->status);
			$sql_pagamento = $db->prepare("UPDATE `pagamentos` SET `id_user` = ?, `id_reserva` = ?, `pagamento_id` = ?, `pagamento_metodo` = ?, `pagamento_valor` = ?, `pagamento_parcelas` = ?, `pagamento_status` = ? WHERE `id_user` = '".$reserva['id_usuario']."' AND `external_reference` = '".$reserva['codigo_reserva']."'");
			$sql_pagamento->execute($dados_pagamento);

		}

	}