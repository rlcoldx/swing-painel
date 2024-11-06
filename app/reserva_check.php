<?php 
	include('../config/config.php');

    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
    header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");

    $_POST = json_decode(file_get_contents("php://input"), true);

    if(@$_POST['token'] == TOKEN){

        $dados = array($_POST['id_usuario'], $_POST['codigo_reserva']);
        $sql_check = $db->prepare("SELECT * FROM reservas WHERE id_usuario = ? AND codigo_reserva = ?");
        $sql_check->execute($dados);
        $check = $sql_check->fetch(PDO::FETCH_ASSOC);

        if ($check["status_reserva"] !== 'Pendente') {
			$json = array("result" => "OK");
		}else{
			$json = array("result" => "WAIT");
		}
		
        $json = json_encode($json);
		echo $json;

	}else{
		echo 'Sem autorização';
	}