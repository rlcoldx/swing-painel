<?php 
	include('../config/config.php');

    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
    header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");

    $_POST = json_decode(file_get_contents("php://input"), true);

    if(@$_POST['token'] == TOKEN){

	    $json = "";

			$sql_reservas = $db->prepare("
			  SELECT
			    r.*,
			    p.pagamento_status,
			    p.pagamento_metodo,
			    p.pagamento_valor
			  FROM reservas r
			  LEFT JOIN (
			    SELECT p.*
			    FROM pagamentos p
			    INNER JOIN (
			      SELECT id_reserva, MAX(date_create) AS max_date
			      FROM pagamentos
			      WHERE pagamento_status = 'approved'
			      GROUP BY id_reserva
			    ) ult ON ult.id_reserva = p.id_reserva AND ult.max_date = p.date_create
			  ) p ON p.id_reserva = r.id
			  WHERE
			    r.status_reserva = 'Aceito'
			    AND r.id_suite = :id_suite AND r.interacao_reserva = 'S'
			    AND COALESCE(
			      STR_TO_DATE(r.data_reserva, '%Y-%m-%d %H:%i:%s'),
			      STR_TO_DATE(r.data_reserva, '%Y-%m-%d %H:%i'),
			      STR_TO_DATE(r.data_reserva, '%Y-%m-%d'),
			      STR_TO_DATE(r.data_reserva, '%d/%m/%Y %H:%i:%s'),
			      STR_TO_DATE(r.data_reserva, '%d/%m/%Y %H:%i'),
			      STR_TO_DATE(r.data_reserva, '%d/%m/%Y'),
			      STR_TO_DATE(r.chegada_reserva, '%Y-%m-%d %H:%i:%s'),
			      STR_TO_DATE(r.chegada_reserva, '%d/%m/%Y %H:%i:%s')
			    ) >= (NOW() + INTERVAL 1 HOUR)
			  ORDER BY r.id DESC
			");
			$sql_reservas->execute([':id_suite' => $_POST['id_suite']]);
			$reservas = $sql_reservas->fetchAll(PDO::FETCH_ASSOC);

		if($sql_reservas){

			$linhas = $reservas;			
			$json = $linhas;

		}else{

			$json = array("result" => "error");

		}

		$json = json_encode($json);
		echo $json;

	}else{
		echo 'Sem autorização';
	}