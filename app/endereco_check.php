<?php
    include('../config/config.php');

    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
    header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");

    $_REQUEST = json_decode(file_get_contents('php://input'), true);

    $dados = @array($_REQUEST['id']);

    $sql_login = $db->prepare("SELECT * FROM usuarios WHERE id = ? AND `status` = 'Ativo'");
    $sql_login->execute($dados);
    $login = $sql_login->fetch(PDO::FETCH_ASSOC);

    $json = "";

    if(!empty($login['id'])){

        $result =  array("result"=>"OK");
        $json = json_encode($login);
        $json = json_encode(array_merge(json_decode($json, true),$result));

    }else{
        
        $result = array("result"=>"error");
        $json = json_encode($result);

    }

    echo $json;

   

?>