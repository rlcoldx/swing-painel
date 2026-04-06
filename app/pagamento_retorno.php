<?php
    include('../config/config.php');

    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
    header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");

    // Decodifica a entrada JSON para um array associativo
    $_POST = json_decode(file_get_contents('php://input'), true);

    if(isset($_POST['data'])){

        $id = $_POST['data']['id'];
       
        $curl = curl_init();
        
        curl_setopt_array($curl, array(
          CURLOPT_URL => 'https://api.mercadopago.com/v1/payments/'.$id,
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => '',
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => 'GET',
          CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . ACCESSTOKEN
          ),
        ));
        
        $response = curl_exec($curl);
        curl_close($curl);

        $payment = json_decode($response, true); // Decodifica a resposta JSON para um array associativo

        if(isset($payment['id'])){
          
          $external_reference = $payment['external_reference'];
          $pagamento_status = $payment['status'];

          $dados_pagamento = array($payment['status']);
          $sql_pagamento = $db->prepare("UPDATE `pagamentos` SET `pagamento_status` = ? WHERE `pagamento_id` = ?");
          $sql_pagamento->execute([$payment['status'], $payment['id']]);

          if ($pagamento_status === 'approved' && function_exists('fidelidade_creditar_por_pagamento_aprovado')) {
              $q = $db->prepare("SELECT id_reserva, pagamento_valor FROM pagamentos WHERE pagamento_id = ? LIMIT 1");
              $q->execute([$payment['id']]);
              $rowPag = $q->fetch(PDO::FETCH_ASSOC);
              if ($rowPag && !empty($rowPag['id_reserva'])) {
                  fidelidade_creditar_por_pagamento_aprovado(
                      $db,
                      (int) $rowPag['id_reserva'],
                      (float) $rowPag['pagamento_valor']
                  );
              }
          }

        }
    }
?>