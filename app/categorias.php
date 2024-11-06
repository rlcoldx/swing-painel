<?php 
	include('../config/config.php');

    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
    header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");

    $_POST = json_decode(file_get_contents("php://input"), true);

    if(@$_POST['token'] == TOKEN){

	    $json = "";

		$sql_categorias = $db->prepare("SELECT rc.*, c.cat_imagem FROM restaurantes_categorias AS rc
										INNER JOIN categorias AS c ON c.id = rc.id_categoria
										WHERE rc.nivel = 0 GROUP BY rc.id_categoria ORDER BY rc.nome ASC");
		$sql_categorias->execute();
		$categorias = $sql_categorias->fetchAll(PDO::FETCH_ASSOC);

		if($sql_categorias){

			$json = $categorias;

		}else{

			$json = array("result" => "error");

		}

		$json = json_encode($json);
		echo $json;

	}else{
		echo 'Sem autorização';
	}