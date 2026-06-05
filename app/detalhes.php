<?php 
include('../config/config.php');

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");

$_POST = json_decode(file_get_contents("php://input"), true);

if(@$_POST['token'] == TOKEN){

    $json = "";

	$sql_suites = $db->prepare("SELECT * FROM suites WHERE `status` = 'Publicado' AND id = '".$_POST['id_suite']."'");
	$sql_suites->execute();
	$suites = $sql_suites->fetchAll(PDO::FETCH_ASSOC);

	if($sql_suites){

		$linhas = $suites;

		$i = 0;

		foreach ($suites as $suite) {

			//PEGA AS IMAGENS
			$sql_imagem = $db->prepare("SELECT imagem as image, imagem as thumbImage FROM suites_imagens WHERE id_suite = '".$suite['id']."' ORDER BY `order`,`id`,`data` ASC");
			$sql_imagem->execute();
			$imagem = $sql_imagem->fetchAll(PDO::FETCH_ASSOC);

			$linhas[$i]['imagens'] = $imagem;

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