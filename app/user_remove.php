<?php
    include('../config/config.php');

    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
    header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");

    $_POST = json_decode(file_get_contents('php://input'), true);

    if(@$_POST['token'] == TOKEN){

        $dados_save = @array($_POST['id']);
        $sql_item = $db->prepare("UPDATE `usuarios` SET `status` = 'Inativo' WHERE `id` = ?");
        $sql_item->execute($dados_save);

        $result =  array("result"=>"OK");
        $json = json_encode($result);

        echo $json;

    }else{
        echo 'Sem autorização';
    }
