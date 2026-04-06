<?php 
	include('../config/config.php');

    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
    header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");

    $_POST = json_decode(file_get_contents("php://input"), true);

    if(@$_POST['token'] == TOKEN){

		$idUsuario = (int) @$_POST['id_usuario'];
		if ($idUsuario <= 0) {
			$json = array("result" => "error", "message" => "id_usuario inválido");
			echo json_encode($json);
		} else {

			if (!fidelidade_tabelas_existem($db)) {
				$json = array(
					"result" => "success",
					"movimentacoes" => array(),
					"programa_ativo" => false,
				);
				echo json_encode($json);
			} else {

				$limite = isset($_POST['limite']) ? (int) $_POST['limite'] : 50;
				$limite = max(1, min(200, $limite));

				$st = $db->prepare(
					'SELECT id, pontos, tipo, descricao, id_reserva, id_resgate, criado_em
					 FROM fidelidade_movimentacao
					 WHERE id_usuario = ?
					 ORDER BY criado_em DESC, id DESC
					 LIMIT ' . $limite
				);
				$st->execute(array($idUsuario));
				$rows = $st->fetchAll(PDO::FETCH_ASSOC);

				foreach ($rows as &$r) {
					$r['id'] = (int) $r['id'];
					$r['pontos'] = (int) $r['pontos'];
					$r['id_reserva'] = $r['id_reserva'] !== null ? (int) $r['id_reserva'] : null;
					$r['id_resgate'] = $r['id_resgate'] !== null ? (int) $r['id_resgate'] : null;
				}

				$json = array(
					"result" => "success",
					"movimentacoes" => $rows,
					"programa_ativo" => true,
				);
				echo json_encode($json);
			}
		}

	}else{
		echo 'Sem autorização';
	}
