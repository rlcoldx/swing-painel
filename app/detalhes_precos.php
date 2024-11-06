<?php 
	include('../config/config.php');

    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
    header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");

    $_POST = json_decode(file_get_contents("php://input"), true);

    if(@$_POST['token'] == TOKEN){

	    $json = "";
	    $linhas = "";
	
		if(@$_POST['selectDate']){

        	$diaDaSemana = diaSemana($_POST['selectDate']);

			//VERIFICA SE NAO ESTA FECHADO NO DIA ESCOLHIDO
			$sql_horarios = $db->prepare("SELECT * FROM suites_precos WHERE id_suite = '".$_POST['id_suite']."' AND dias LIKE '%".$diaDaSemana."%' AND `status` = 'S' ORDER BY `periodo` ASC");
			$sql_horarios->execute();

			if($sql_horarios->rowCount() != 0){

				$horarios = $sql_horarios->fetchAll(PDO::FETCH_ASSOC);
				$linhas = $horarios;

				$json = json_encode($linhas);
				echo $json;

			}else{

				$json = json_encode(array());
				echo $json;
			}

		}else{
			echo 'Sem autorização';
		}

	}else{
		echo 'Sem autorização';
	}