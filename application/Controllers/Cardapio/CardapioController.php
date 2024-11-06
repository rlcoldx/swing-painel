<?php

namespace Agencia\Close\Controllers\Cardapio;

use Agencia\Close\Models\Suites\Suites;
use Agencia\Close\Controllers\Controller;

class CardapioController extends Controller
{
  public function index()
  {

    $imagens = new Suites();
    $imagem = $imagens->getCardadio()->getResult();

    $this->render('pages/cardapio/index.twig', ['titulo' => 'Cardápio', 'imagens' => $imagem]);
  }

}