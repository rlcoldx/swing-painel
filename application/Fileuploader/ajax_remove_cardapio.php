<?php
	include('../../config/db.php');

	if (isset($_POST['file'])) {

		$sql_item = $db->prepare("SELECT * FROM cardapios WHERE nome = '".$_POST['file']."'");
		$sql_item->execute();
		$item = $sql_item->fetch(PDO::FETCH_ASSOC);
		
		$caminho = explode('-', $item['data']);

		$diretorio = '../../uploads/cardapios/';

		$file = $diretorio.$item['nome'];
		
		if(file_exists($file))
			unlink($file);

		$sql = $db->prepare("DELETE FROM `cardapios` WHERE id = '".$item['id']."'");
		$sql->execute();

	}

	echo $file;