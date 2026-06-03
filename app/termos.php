<?php 
include('../config/config.php');

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");

$_POST = json_decode(file_get_contents("php://input"), true);

$linhas = [];

//PEGA OS TERMOS
$sql_termos = $db->prepare("SELECT * FROM paginas WHERE id = 1");
$sql_termos->execute();
$termos = $sql_termos->fetch(PDO::FETCH_ASSOC);

$linhas[0]['termos'] = $termos['conteudo'];

$json = $linhas;

$json = json_encode($json);
echo $json;