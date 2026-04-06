<?php 
	include('../config/config.php');

    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
    header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");

    $_POST = json_decode(file_get_contents("php://input"), true);

    if(@$_POST['token'] == TOKEN){

		$idUsuario = (int) @$_POST['id_usuario'];
		if ($idUsuario <= 0) {
			$json = array("result" => "error", "message" => "id_usuario inválido");
			echo json_encode($json);
		} else {

			$resumo = fidelidade_resumo_usuario($db, $idUsuario);
			$regras = fidelidade_regras_publicas();

			$json = array(
				"result" => "success",
				"saldo" => $resumo['saldo'],
				"total_ganho" => $resumo['total_ganho'],
				"total_gasto" => $resumo['total_gasto'],
				"regras" => $regras,
				"programa_ativo" => fidelidade_tabelas_existem($db),
			);
			echo json_encode($json);
		}

	}else{
		echo 'Sem autorização';
	}
