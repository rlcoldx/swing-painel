<?php

namespace Agencia\Close\Controllers\Suites;

use Agencia\Close\Controllers\Controller;
use Agencia\Close\Models\Suites\Suites;
use Agencia\Close\Enums\Permissions\ProductsPermissions;
use Agencia\Close\Services\Sis\CategoriesSis;

class SuitesController extends Controller
{
  private function sisViewData(): array
  {
    if (!defined('SIS_ATIVO') || !SIS_ATIVO) {
      return ['sis_ativo' => false, 'sis_categories' => []];
    }

    $categories = (new CategoriesSis())->listCategories();
    return [
      'sis_ativo' => true,
      'sis_categories' => $categories['result'] ?? [],
    ];
  }

  public function tempermissao() {
    echo 'Tem permissao';
    $this->requirePermission(ProductsPermissions::$listProduct);
    die();
  }

  public function sempermissao() {
    $this->requirePermission(ProductsPermissions::$createProduct);
    echo 'Você não pode ver isso';
    die();
  }

  public function index($params)
  {
    $this->setParams($params);

    $suites = new Suites();
    $suites = $suites->getSuites()->getResult();

    $this->render('pages/suites/index.twig', ['titulo' => 'Minhas Suítes', 'suites' => $suites]);
  }

  public function criar($params)
  {
    $this->setParams($params);
    $this->render('pages/suites/form.twig', array_merge(
      ['titulo' => 'Criar Suíte'],
      $this->sisViewData()
    ));
  }

  public function editar($params)
  {
    $this->setParams($params);

    $suite = new Suites();
    $result = $suite->getSuite($this->params['id']);
    $suite = $result->getResult()[0];

    $precos = new Suites();
    $precos = $precos->getSuitePrecos($this->params['id'])->getResult();

    $imagens = new Suites();
    $imagem = $imagens->getSuiteImages($this->params['id'])->getResult();

    $this->render('pages/suites/form.twig', array_merge(
      ['titulo' => 'Editar Suíte', 'suite' => $suite, 'precos' => $precos, 'imagens' => $imagem],
      $this->sisViewData()
    ));
  }

  //CRIAR O PRODUTO EM RASCUNHO
  public function save_draft($params)
  {
    $this->setParams($params);
    $suites = new Suites();
    $result = $suites->createDraft($this->params);
    $suite_draft = $result->getResult()[0];

    header("Content-Type: application/json");
    echo json_encode($suite_draft);
  }

  //SALVA O EDITAR DA SUITE
  public function save_edit($params)
  {
    $this->setParams($params);
    $suites = new Suites();
    $result = $suites->saveEdit($this->params)->getResult();
    
    if($this->params['price_chance'] == 'sim') {
      $precos = new Suites();
      $precos->saveEditPrecos($this->params['preco'], $this->params['id']);
    }

    if($result){
      echo 'success';
    }else{
      echo 'error';
    }

  }

  //EXCLUI A SUITE
  public function excluir_suite($params)
  {
    $this->setParams($params);
    $excluir = new Suites();
    $excluir->excluirSuite($this->params['id_suite']);
  }

  public function duplicar($params)
  {
    $this->setParams($params);
    $duplicar = new Suites();
    $duplicar->duplicarSuite($_GET['id']);
  }

}