<?php

namespace Agencia\Close\Models\Sis;

use Agencia\Close\Conn\Update;
use Agencia\Close\Models\Model;

class Sis extends Model
{
    public function updateDisponibilidade(array $suite): void
    {
        if (empty($suite['id'])) {
            return;
        }

        $update = new Update();
        $update->ExeUpdate(
            'suites',
            [
                'quantidade' => (int) ($suite['total'] ?? 0),
                'disponibilidade' => (int) ($suite['free'] ?? 0),
            ],
            "WHERE `sis_suite` = :sis_suite_id AND `sis_suite` IS NOT NULL AND `sis_suite` <> ''",
            'sis_suite_id=' . (int) $suite['id']
        );
    }
}
