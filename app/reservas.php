<?php 
	include('../config/config.php');

    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
    header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");

    $_POST = json_decode(file_get_contents("php://input"), true);

    if(@$_POST['token'] == TOKEN){

	    $json = "";

		$sql_reservas = $db->prepare("SELECT r.*, p.pagamento_status, p.pagamento_metodo, p.pagamento_valor FROM reservas AS r
									LEFT JOIN pagamentos AS p ON p.id_reserva = r.id
									WHERE r.id_usuario = '".$_POST['id_usuario']."' ORDER BY r.id DESC");
		$sql_reservas->execute();
		$reservas = $sql_reservas->fetchAll(PDO::FETCH_ASSOC);

		if($sql_reservas){

			$linhas = $reservas;
			$i = 0;

			foreach ($reservas as $reserva) {

				//PEGA A IMAGEM DE CAPA
				$sql_suite = $db->prepare("SELECT * FROM suites WHERE id = '".$reserva['id_suite']."'");
				$sql_suite->execute();
				$suite = $sql_suite->fetch(PDO::FETCH_ASSOC);

				$linhas[$i]['suite'] = $suite;
				if($linhas[$i]['pagamento_status'] == null){
					$linhas[$i]['pagamento_status_cor'] = corStatusPagamento($reserva['status_reserva']);
					$linhas[$i]['pagamento_status'] = traduzirStatusPagamento($reserva['status_reserva']);
					if($reserva['status_reserva'] == 'Pendente'){
						$linhas[$i]['link_page'] = 'espera';
					}else if ($reserva['status_reserva'] == 'Recusado'){
						$linhas[$i]['link_page'] = 'recusado';
					}else{
						$linhas[$i]['link_page'] = 'pagamento';
					}

				}else{
					$linhas[$i]['pagamento_status_cor'] = corStatusPagamento($linhas[$i]['pagamento_status']);
					$linhas[$i]['pagamento_status'] = traduzirStatusPagamento($linhas[$i]['pagamento_status']);
					$linhas[$i]['link_page'] = 'reserva-detalhe';
				}

				//PEGA A IMAGEM DE CAPA
				$sql_imagem = $db->prepare("SELECT * FROM suites_imagens WHERE id_suite = '".$reserva['id_suite']."' AND `order` = 0 ORDER BY `order`,`id`,`data` ASC");
				$sql_imagem->execute();
				$imagem = $sql_imagem->fetch(PDO::FETCH_ASSOC);

				$linhas[$i]['imagem'] = $imagem['imagem'];

				$i++;
			}
			
			$json = $linhas;

		}else{

			$json = array("result" => "error");

		}

		$json = json_encode($json);
		echo $json;

	}else{
		echo 'Sem autorização';
	}