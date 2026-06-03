<?php

$codigo_pedido = gerarCodigoPedido();

if (empty($_POST['pontos_fidelidade'])) {
	$_POST['pontos_fidelidade'] = 0;
}
if (empty($_POST['valor_reserva_total'])) {
	$_POST['valor_reserva_total'] = '0.00';
}

$sql_suite = $db->prepare('SELECT sis_suite FROM suites WHERE id = ? LIMIT 1');
$sql_suite->execute([$_POST['id_suite']]);
$suite = $sql_suite->fetch(PDO::FETCH_ASSOC);

if (!$suite || empty($suite['sis_suite'])) {
	$json = ['result' => 'error', 'message' => 'Suíte sem vínculo SIS configurado'];
	return;
}

$dateScheduled = sis_montar_date_scheduled(
	$_POST['data_reserva'],
	$_POST['chegada_reserva'],
	$_POST['periodo_reserva']
);

$valorReserva = number_format((float) str_replace(',', '.', preg_replace('/[^\d.,-]/', '', (string) $_POST['valor_reserva'])), 2, '.', '');

$payload = [
	'categories_id' => (int) $suite['sis_suite'],
	'period' => converterParaMinutos($_POST['periodo_reserva']),
	'tolerance' => 15,
	'discount' => 0,
	'value' => $valorReserva,
	'value_receive' => 0,
	'value_extra' => 0,
	'charging' => 1,
	'cpf_client' => limparCPF($_POST['cpf'] ?? ''),
	'name_client' => $_POST['nome'] ?? '',
	'phone_client' => $_POST['telefone'] ?? '',
	'email_client' => $_POST['email'] ?? '',
	'date_scheduled' => $dateScheduled,
	'message' => 'BUSCA DE MOTEIS: ' . $codigo_pedido,
	'note' => '',
	'coupon' => 0,
];

$sisResponse = sis_criar_reserva($payload);

if (empty($sisResponse['success']) || empty($sisResponse['result']['id'])) {
	$message = $sisResponse['message'] ?? 'Erro ao criar reserva no SIS';
	$json = ['result' => 'error', 'message' => $message];
	return;
}

$idReservaSis = (int) $sisResponse['result']['id'];
$statusSis = (int) ($sisResponse['result']['situation'] ?? 1);

$dados = [
	$_POST['id_suite'],
	$_POST['data_reserva'],
	$_POST['chegada_reserva'],
	$_POST['periodo_reserva'],
	$_POST['valor_reserva'],
	$codigo_pedido,
	$_POST['cupom'],
	$_POST['id_usuario'],
	$_POST['nome'],
	$_POST['email'],
	$_POST['telefone'],
	$_POST['cpf'],
	$_POST['pontos_fidelidade'],
	$_POST['valor_reserva_total'],
	'sis',
	$idReservaSis,
	$statusSis,
];

$sql_reserva = $db->prepare(
	"INSERT INTO reservas (`id_suite`,`data_reserva`,`chegada_reserva`,`periodo_reserva`,`valor_reserva`,`codigo_reserva`,`cupom_reserva`,`id_usuario`,`nome`,`email`,`telefone`,`cpf`,`pontos_fidelidade`,`valor_reserva_total`,`integracao`,`id_reserva_sis`,`status_sis`) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
);
$sql_reserva->execute($dados);

if ($sql_reserva) {
	$sql_last = $db->prepare("SELECT * FROM reservas WHERE `id_usuario` = ? ORDER BY id DESC LIMIT 1");
	$sql_last->execute([$_POST['id_usuario']]);
	$json = $sql_last->fetchAll(PDO::FETCH_ASSOC);
} else {
	sis_cancelar_reserva($idReservaSis);
	$json = ['result' => 'error', 'message' => 'Erro ao gravar reserva local'];
}
