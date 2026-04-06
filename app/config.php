<?php 
	include('../config/config.php');

    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
    header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");

    $_POST = json_decode(file_get_contents("php://input"), true);

    if(@$_POST['token'] == TOKEN){

		$sql_config = $db->prepare("SELECT * FROM configuracoes LIMIT 1");
		$sql_config->execute();
		$config = $sql_config->fetchAll(PDO::FETCH_ASSOC);

		$json = json_encode($config);
		echo $json;

	}else{
		echo 'Sem autorização';
	}