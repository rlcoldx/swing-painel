<?php 
	include('../config/config.php');

    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
    header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");

    $_POST = json_decode(file_get_contents("php://input"), true);

    $json = "";

    if(!empty($_POST['id_pagina'])){

		$sql_paginas = $db->prepare("SELECT * FROM paginas WHERE id = '".$_POST['id_pagina']."' AND `status` = 'Publicado'");
		$sql_paginas->execute();
		$paginas = $sql_paginas->fetch(PDO::FETCH_ASSOC);

		if($sql_paginas){
			$json = $paginas;
		}else{
			$json = array("result" => "error");
		}
    
		$json = json_encode($json);
		echo $json;
    }

