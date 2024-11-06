<?php
// PAGE HOME
$router->namespace("Agencia\Close\Controllers\Home");
$router->get("/", "HomeController:index");
$router->get("/tempermissao", "HomeController:tempermissao");
$router->get("/sempermissao", "HomeController:sempermissao");