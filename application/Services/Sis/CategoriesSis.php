<?php

namespace Agencia\Close\Services\Sis;

class CategoriesSis
{
    public function listCategories(?string $token = null): array
    {
        if (!defined('SIS_ATIVO') || !SIS_ATIVO) {
            return [];
        }

        $token = $token ?: (defined('SIS_KEY') ? SIS_KEY : '');

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => SIS_API . '/api/categories',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => [
                'token: ' . $token,
                'softhouse: ' . SOFTHOUSE,
            ],
        ]);
        $response = curl_exec($curl);
        curl_close($curl);

        return json_decode((string) $response, true) ?: [];
    }
}
