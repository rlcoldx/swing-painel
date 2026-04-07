<?php 
	include('../config/config.php');
	if (!function_exists('fidelidade_processar_resgate_app')) {
		include_once(dirname(__DIR__) . '/config/fidelidade.php');
	}

    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
    header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");

    $_POST = json_decode(file_get_contents("php://input"), true);

    if(@$_POST['token'] == TOKEN){

		$idUsuario = (int) @$_POST['id_usuario'];
		$tipo = isset($_POST['tipo']) ? trim((string) $_POST['tipo']) : '';
		$idSuite = isset($_POST['id_suite']) ? (int) $_POST['id_suite'] : null;

		if ($idUsuario <= 0 || $tipo === '') {
			$json = array("result" => "error", "message" => "Informe id_usuario e tipo (suite, alimento ou bebida).");
			echo json_encode($json);
		} else {

			$out = fidelidade_processar_resgate_app($db, $idUsuario, $tipo, $idSuite);

			if (!$out['ok']) {
				$json = array(
					"result" => "error",
					"message" => isset($out['message']) ? $out['message'] : 'Resgate não realizado',
				);
				echo json_encode($json);
			} else {

				$resumo = fidelidade_resumo_usuario($db, $idUsuario);

				$json = array(
					"result" => "success",
					"message" => "Resgate registrado. Aguarde confirmação no local (sujeito à disponibilidade).",
					"id_resgate" => $out['id_resgate'],
					"pontos_debitados" => $out['pontos_debitados'],
					"saldo_atual" => $resumo['saldo'],
				);
				echo json_encode($json);
			}
		}

	}else{
		echo 'Sem autorização';
	}
