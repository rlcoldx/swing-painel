<?php
    include('../config/config.php');

    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
    header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");

    $_REQUEST = json_decode(file_get_contents('php://input'), true);

    $json = "";

    $cep = @$_REQUEST['cep'];
    $cep = preg_replace('/\D/', '', $cep);

    $url = "http://viacep.com.br/ws/{$cep}/json/";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    $dados = json_decode($response, true);

    if (!isset($dados['erro'])) {
        $endereco = @$dados['logradouro'];
        $bairro = @$dados['bairro'];
        $cidade = @$dados['localidade'];
        $estado = @$dados['uf'];

        // Retornar os dados em formato JSON
        $retorno = @array(
            'endereco' => $endereco,
            'bairro' => $bairro,
            'cidade' => $cidade,
            'estado' => $estado
        );

        $result =  array("result"=>"OK");
        $json = json_encode($retorno);
        $json = json_encode(array_merge(json_decode($json, true),$result));
    }

    echo $json;