<?php

namespace Agencia\Close\Controllers\Sis;

use Agencia\Close\Controllers\Controller;
use Agencia\Close\Models\Sis\Sis as SisModel;
use Agencia\Close\Services\Sis\CategoriesSis;

class SisController extends Controller
{
    public function disponibilidade($params): void
    {
        $this->setParams($params);

        if (!defined('SIS_ATIVO') || !SIS_ATIVO) {
            return;
        }

        $categories = (new CategoriesSis())->listCategories();
        if (empty($categories['result']) || !is_array($categories['result'])) {
            return;
        }

        $sis = new SisModel();
        foreach ($categories['result'] as $suite) {
            $sis->updateDisponibilidade($suite);
        }
    }
}
