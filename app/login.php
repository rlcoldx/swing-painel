<?php
    include('../config/config.php');

    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
    header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");

    $_REQUEST = json_decode(file_get_contents('php://input'), true);


    if(isset($_REQUEST["email"]) || isset($_REQUEST["senha"]) ){
    	if( !empty($_REQUEST["email"])  || !empty($_REQUEST["senha"]) ){


            $dados = @array($_REQUEST['email'],sha1($_REQUEST['senha']));

            $sql_login = $db->prepare("SELECT * FROM usuarios WHERE email = ? AND senha = ?");
            $sql_login->execute($dados);
            $login = $sql_login->fetch(PDO::FETCH_ASSOC);

            $json = "";

            if(!empty($login['id'])){

                $result =  array("result"=>"OK");
                $json = json_encode($login);
                $json = json_encode(array_merge(json_decode($json, true),$result));
                echo $json;

            }else{
                
                $result = array("result"=>"error");
                $json = json_encode($result);
                echo $json;

            }


    	}
    }

?>