$(document).ready(function () {

	$('#enviar_notificacao').submit(function(e){

		e.preventDefault();
		var domain = $('body').data('domain');
		var formData = new FormData(this);
		$('#salvar').prop('type', 'button');

		$.ajax({
			url: domain + '/notificacao/enviar',
			data: formData,
			type: 'POST',
			success: function(data){
				if(data !== 'error'){
					Swal.fire('', 'ENVIADO COM SUCESSO!', 'success');
					setTimeout(function(){
						location.reload();
					}, 1500);
				}else{
					Swal.fire('Erro', data, 'error');
				}
			},
			error: function(xhr, status, error){
				Swal.fire('Erro na requisição', xhr.responseText || error, 'error');
			},
			processData: false,
			cache: false,
			contentType: false
		});
	});

});
