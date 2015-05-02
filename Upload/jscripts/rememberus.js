$(document).ready(function($)
{
	$('.rememberus_btn_delete').click(function(){
		var removeRow = $(this).parent().parent();
		$(removeRow).remove();
	});

	$('.rememberus_btn_add').click(function(){
		var addRow = $(this).parent().parent();
		var cloneRow = $(addRow).clone(true);

		// do some clean up
		$('.form_container tbody tr.first').removeClass('first');

		// Add row
		var tableContainer = $(addRow).parent();
		$(cloneRow).appendTo(tableContainer);

		// replace the button in the row
		//$(cloneRow).chilren('.last').chilren('.rememberus_btn_add').removeClass('rememberus_btn_add').addClass('rememberus_btn_delete');
		// didn't work..

		return false;
	});
});