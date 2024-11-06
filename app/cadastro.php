<?php 
	include('../config/config.php');

    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
    header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");

    $_POST = json_decode(file_get_contents("php://input"), true);

    $json = "";

	$dados = @array($_POST['email']);
	$sql_check_email = $db->prepare("SELECT * FROM usuarios WHERE email = ?");
	$sql_check_email->execute($dados);

	if($sql_check_email->rowCount() > 0){
		
		$result = array("result" => "505");
        $json = json_encode($result);

	}else{

		if(isset($_POST["email"]) || isset($_POST["senha"]) ){
			if( !empty($_POST["email"])  || !empty($_POST["senha"]) ){
				
				$dados = array($_POST['nome'], strtolower($_POST['email']), sha1($_POST['senha']), $_POST['cpf'], $_POST['telefone'], 'Ativo');
				$sql_cadastro = $db->prepare("INSERT INTO usuarios (`nome`, `email`, `senha`,  `cpf`,  `telefone`, `status`) VALUES (?, ?, ?, ?, ?, ?)");
				$sql_cadastro->execute($dados);

				if($sql_cadastro){

					$dados = array($_POST['email'],sha1($_POST['senha']));
					$sql_login = $db->prepare("SELECT * FROM usuarios WHERE email = ? AND senha = ?");
					$sql_login->execute($dados);
					$login = $sql_login->fetch(PDO::FETCH_ASSOC);


					if(!empty($login['id'])){

						$result =  array("result"=>"OK");
						$json = json_encode($login);
						$json = json_encode(array_merge(json_decode($json, true),$result));

					}

				}else{

					$result = array("result" => "error");
       				$json = json_encode($result);


				}
			

			}
		}

	}

	echo $json;
	
?>