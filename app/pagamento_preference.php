<?php 
	include('../config/config.php');

    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
    header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");

    $_POST = json_decode(file_get_contents("php://input"), true);

    if(@$_POST['token'] == TOKEN){

		$dados = array($_POST['codigo_reserva']);
		$sql_reserva = $db->prepare("SELECT r.*, DATE_FORMAT(r.data_reserva, '%d-%m-%Y') AS data_escolhida, s.nome as suite_nome FROM reservas AS r
									INNER JOIN suites AS s ON s.id = r.id_suite
									WHERE r.codigo_reserva = ?");
		$sql_reserva->execute($dados);
		$reserva = $sql_reserva->fetch(PDO::FETCH_ASSOC);


		$curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.mercadopago.com/checkout/preferences',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => '{
                "back_urls": {
                    "success": "https://api.whatsapp.com/send?phone=5511978270494",
                    "pending": "https://api.whatsapp.com/send?phone=5511978270494",
                    "failure": "https://api.whatsapp.com/send?phone=5511978270494"
                },
                "external_reference": "' .$reserva['codigo_reserva']. '",
                "notification_url": "https://rafael-dev.pro/projetos/motelvianna/app/pagamento_retorno.php",
                "auto_return": "approved",
                "items": [
                    {
                        "title": "'.$reserva['suite_nome'].' : '.$reserva['nome'].'",
                        "description": "'.$reserva['data_escolhida'].' - '.$reserva['chegada_reserva'].' - '.$reserva['periodo_reserva'].'",
                        "quantity": 1,
                        "currency_id": "BRL",
                        "unit_price": '.$reserva['valor_reserva'].'
                    }
                ],
                "payment_methods": {
                    "excluded_payment_types": [
                        {"id": "ticket"}
                    ]
                }
            }',
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'X-Idempotency-Key: vianna-reserva-'.$reserva['codigo_reserva'],
                'Authorization: Bearer ' . ACCESSTOKEN
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);

        $obj = json_decode($response);

        if (isset($obj->id)) {
            if ($obj->id != NULL) {
            	$json[] = array("id" => $obj->id);
            	$json = json_encode($json);
				echo $json;
            }
        }

	}else{
		echo 'Sem autorização';
	}