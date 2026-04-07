<?php 
	include('../config/config.php');
	if (!function_exists('fidelidade_regras_publicas')) {
		include_once(dirname(__DIR__) . '/config/fidelidade.php');
	}

    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
    header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");

    $_POST = json_decode(file_get_contents("php://input"), true);

    if(@$_POST['token'] == TOKEN){

		$json = array(
			"result" => "success",
			"regras" => fidelidade_regras_publicas(),
			"programa_ativo" => fidelidade_tabelas_existem($db),
		);
		echo json_encode($json);

	}else{
		echo 'Sem autorização';
	}
