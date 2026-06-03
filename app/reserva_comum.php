<?php

$codigo_pedido = gerarCodigoPedido();

if (empty($_POST['pontos_fidelidade'])) {
	$_POST['pontos_fidelidade'] = 0;
}
if (empty($_POST['valor_reserva_total'])) {
	$_POST['valor_reserva_total'] = '0.00';
}

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
];

$sql_reserva = $db->prepare(
	"INSERT INTO reservas (`id_suite`,`data_reserva`,`chegada_reserva`,`periodo_reserva`,`valor_reserva`,`codigo_reserva`,`cupom_reserva`,`id_usuario`,`nome`,`email`,`telefone`,`cpf`,`pontos_fidelidade`,`valor_reserva_total`) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
);
$sql_reserva->execute($dados);

if ($sql_reserva) {
	$sql_last = $db->prepare("SELECT * FROM reservas WHERE `id_usuario` = ? ORDER BY id DESC LIMIT 1");
	$sql_last->execute([$_POST['id_usuario']]);
	$json = $sql_last->fetchAll(PDO::FETCH_ASSOC);
} else {
	$json = ['result' => 'error'];
}
