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

        $payment = json_decode($response, true, 512, JSON_BIGINT_AS_STRING);

        if(isset($payment['id'])){
          
          $external_reference = isset($payment['external_reference']) ? trim((string) $payment['external_reference']) : '';
          $pagamento_status = $payment['status'];

          $pid = (string) $payment['id'];

          $dados_pagamento = array($payment['status']);
          $sql_pagamento = $db->prepare("UPDATE `pagamentos` SET `pagamento_status` = ? WHERE `pagamento_id` = ?");
          $sql_pagamento->execute([$payment['status'], $pid]);

          if ($pagamento_status === 'approved') {

              $q = $db->prepare('SELECT id_reserva, pagamento_valor FROM pagamentos WHERE pagamento_id = ? LIMIT 1');
              $q->execute([$pid]);
              $rowPag = $q->fetch(PDO::FETCH_ASSOC);
              if (!$rowPag) {
                  $q2 = $db->prepare('SELECT id_reserva, pagamento_valor FROM pagamentos WHERE pagamento_id = ? LIMIT 1');
                  $q2->execute([(int) $payment['id']]);
                  $rowPag = $q2->fetch(PDO::FETCH_ASSOC);
              }
              if (!$rowPag && $external_reference !== '') {
                  $q3 = $db->prepare('SELECT id_reserva, pagamento_valor FROM pagamentos WHERE external_reference = ? ORDER BY id DESC LIMIT 1');
                  $q3->execute([$external_reference]);
                  $rowPag = $q3->fetch(PDO::FETCH_ASSOC);
              }

              $idReservaKey = null;
              if ($rowPag && isset($rowPag['id_reserva']) && $rowPag['id_reserva'] !== '' && $rowPag['id_reserva'] !== null) {
                  $idReservaKey = (string) $rowPag['id_reserva'];
              } elseif ($external_reference !== '') {
                  $sr = $db->prepare(
                      'SELECT id FROM reservas WHERE codigo_reserva = ? OR external_reference = ? ORDER BY id DESC LIMIT 1'
                  );
                  $sr->execute([$external_reference, $external_reference]);
                  $found = $sr->fetchColumn();
                  if ($found !== false && $found !== null && $found !== '') {
                      $idReservaKey = (string) $found;
                  }
              }

              if ($idReservaKey !== null && $idReservaKey !== '' && $idReservaKey !== '0') {
                  $valor = 0.0;
                  if ($rowPag && isset($rowPag['pagamento_valor']) && $rowPag['pagamento_valor'] !== '' && $rowPag['pagamento_valor'] !== null) {
                      $valor = (float) str_replace(',', '.', preg_replace('/[^\d.,-]/', '', (string) $rowPag['pagamento_valor']));
                  }
                  if ($valor <= 0 && isset($payment['transaction_amount'])) {
                      $valor = (float) $payment['transaction_amount'];
                  }
                  fidelidade_creditar_por_pagamento_aprovado($db, $idReservaKey, $valor);
              }

          }

        }
    }
?>