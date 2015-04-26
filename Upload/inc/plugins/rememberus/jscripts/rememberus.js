var rememberusDeleteFilter = function(e)
{
	var deleteRow = this.up(1);
	Effect.Fade(deleteRow, {'duration': 0.5, 'afterFinish': function(){ deleteRow.remove(); }});
	
	Event.stop(e);
};

Event.observe(document, "dom:loaded", function(){
	// the event listener for the add button
	Event.observe('rememberus_btn_add', 'click', function(e){
		var addRow = this.up(1);
		var cloneRow = addRow.cloneNode(true);
		
		// do some clean up
		cloneRow.removeClassName('first');
		cloneRow.hide();
		cloneRow.select(".first").invoke('removeClassName', 'first');
		
		// the values of the select boxes aren't being copied
		// to the new row so we need to copy the values manually
		var fieldValue = addRow.select('.rememberus_field')[0].value;
		var testValue = addRow.select('.rememberus_test')[0].value;
		cloneRow.select('.rememberus_field')[0].value = fieldValue;
		cloneRow.select('.rememberus_test')[0].value = testValue;
		
		// replace the button in the row
		cloneRow.select('.rememberus_btn_add').invoke('replace','<a href="#" class="rememberus_btn_delete">&nbsp;</a>');
		cloneRow.select('.rememberus_btn_delete').invoke('observe', 'click', rememberusDeleteFilter);
			
		// reset the values of the add row
		addRow.select('.rememberus_field').each(function(f){ f.value = ''; });
		addRow.select('.rememberus_test').each(function(f){ f.value = ''; });
		addRow.select('.rememberus_value').each(function(f){ f.value = ''; });
		
		// insert the row before the add row
		addRow.insert({'before': cloneRow});
		Effect.Appear(cloneRow);
		
		Event.stop(e);
	});
	
	// the event listener for the delete buttons
	$$('.rememberus_btn_delete').invoke('observe', 'click', rememberusDeleteFilter);
	
	// observer for the auto_txt checkbox
	if($('auto_txt'))
	{
		Event.observe('auto_txt', 'click', function(){
			if(this.checked)
			{
				$('reminder_txt').hide();
			}
			else
			{
				$('reminder_txt').show();
			}
		});
	}
	
	$$('.rememberus_btn_help').invoke('observe', 'click', function(e){
		if($(this.rel))
		{
			Effect.toggle(this.rel, 'slide');
		}
		
		Event.stop(e);
	});
	
	$$('.rememberus_help_close').invoke('observe', 'click', function(e){
		//this.up(3).hide();
		Effect.toggle(this.up(3), 'slide');
		Event.stop(e);
	});
	
	$$('.rememberus_help').invoke('hide');
});