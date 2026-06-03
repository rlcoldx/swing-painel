<?php
use CoffeeCode\Router\Router;

$router = new Router(DOMAIN);

require  __DIR__ . '/login.php';
require  __DIR__ . '/home.php';
require  __DIR__ . '/suites.php';
require  __DIR__ . '/equipe.php';
require  __DIR__ . '/cargos.php';
require  __DIR__ . '/reservas.php';
require  __DIR__ . '/sis.php';
require  __DIR__ . '/cardapio.php';
require  __DIR__ . '/clientes.php';
require  __DIR__ . '/config.php';
require  __DIR__ . '/notificacao.php';
require  __DIR__ . '/cupons.php';
require  __DIR__ . '/paginas.php';
require  __DIR__ . '/fidelidade.php';

// ERROR
$router->group("error")->namespace("Agencia\Close\Controllers\Error");
$router->get("/{errorCode}", "ErrorController:show", 'error');

$router->dispatch();
if ($router->error()) {
    echo "Página não encontrada.";
}