<?php
    include('../config/config.php');

    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
    header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");

    $_REQUEST = json_decode(file_get_contents('php://input'), true);

    $dados_save = @array($_REQUEST['cep'], $_REQUEST['endereco'], $_REQUEST['numero'], $_REQUEST['bairro'], $_REQUEST['cidade'], $_REQUEST['estado'], $_REQUEST['id']);
    $sql_item = $db->prepare("UPDATE `usuarios` SET `cep` = ?, `endereco` = ?, `numero` = ?, `bairro` = ?, `cidade` = ?, `estado` = ? WHERE `id` = ?");
    $sql_item->execute($dados_save);
    

    $dados = @array($_REQUEST['id']);
    $sql_user = $db->prepare("SELECT * FROM usuarios WHERE `id` = ? AND `status` = 'Ativo'");
    $sql_user->execute($dados);
    $user = $sql_user->fetch(PDO::FETCH_ASSOC);

    $json = "";

    if(!empty($user['id'])){

        $result =  array("result"=>"OK");
        $json = json_encode($user);
        $json = json_encode(array_merge(json_decode($json, true),$result));

    }else{
        
        $result = array("result"=>"error");
        $json = json_encode($result);

    }

    echo $json;

   

?>