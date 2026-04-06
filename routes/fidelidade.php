<?php

$router->namespace('Agencia\Close\Controllers\Fidelidade');
$router->get('/fidelidade', 'FidelidadeController:index');
$router->get('/fidelidade/usuario/{id}', 'FidelidadeController:usuario');
