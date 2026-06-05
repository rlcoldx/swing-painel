<?php 
	include('../config/config.php');

    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
    header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");

    $_POST = json_decode(file_get_contents("php://input"), true);

    if(@$_POST['token'] == TOKEN){

	    $json = "";

		$sql_suites = $db->prepare("SELECT * FROM suites WHERE `status` = 'Publicado'");
		$sql_suites->execute();
		$suites = $sql_suites->fetchAll(PDO::FETCH_ASSOC);

		if($sql_suites){

			$linhas = $suites;

			$i = 0;

			foreach ($suites as $suite) {

				//PEGA A IMAGEM DE CAPA
				$sql_imagem = $db->prepare("SELECT * FROM suites_imagens WHERE id_suite = '".$suite['id']."' AND `order` = 0 ORDER BY `order`,`id`,`data` ASC");
				$sql_imagem->execute();
				$imagem = $sql_imagem->fetch(PDO::FETCH_ASSOC);

				$linhas[$i]['imagens'] = $imagem['imagem'];

				//PEGA OS HORARIOS DAQUELE DIA
				$sql_precos = $db->prepare("SELECT * FROM suites_precos WHERE id_suite = '".$suite['id']."' AND `status` = 'S' ORDER BY valor ASC LIMIT 1");
				$sql_precos->execute();
				$precos = $sql_precos->fetch(PDO::FETCH_ASSOC);
				$linhas[$i]['apartir'] = $precos['valor'];

				if(SIS_ATIVO){
					$linhas[$i]['integracao'] = 'S';
				}

			$i++;}

			$json = $linhas;

		}else{

			$json = array("result" => "error");

		}

		$json = json_encode($json);
		echo $json;

	}else{
		echo 'Sem autorização';
	}