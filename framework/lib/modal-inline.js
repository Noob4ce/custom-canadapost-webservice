"use strict";
/**
 *  Modal / Workflow specific code
 */
jQuery( function($){
	
	// Tells any anchor with only a hash url to do nothing.
	$("a[href='#']" ).on('click', function( e ){ e.preventDefault(); });
	
	// Prepare form
	$('#form_shipment').ajaxForm({
		dataType:  'json',
		type : 'POST'
	});
	
	// Cancel button to close the modal.
	$('#btn-cancel' ).on( 'click' , parent.cpwebservice_iframe_modal_close_handler );
	
	// Saves data on form as draft.
	var cpwebservice_shipment_savedraft = function(callback) {
		// Submit to wp-ajax.
		nav_loading_show();
		$('#form_shipment').ajaxSubmit({ 
			url : cpwebservice_save_draft_shipment.ajaxurl + '?action=cpwebservice_save_draft_shipment',
			data: { '_wpnonce' : cpwebservice_save_draft_shipment.postNonce, 
				'package_index' : $('#shipment_data').data('packageindex'), 'order_id' : $('#shipment_data').data('orderid') },
			type : 'POST',
			success: function(data) {
				 nav_loading_hide();
				 $('#display_message').html(cpwebservice_save_draft_shipment.success + ': ' + data);
				 if (data == 'true') { 
					 add_success_icon();
				 }
				 if (callback && callback!=null){
					 callback();
				 }
	        }
		});
	};
	// Wire up Save Draft btn
	$('#btn-draft').on('click', function() { 
		// Save Draft.
		cpwebservice_shipment_savedraft();
		// Refresh order action rows on click.
		parent.cpwebservice_order_refresh();
	});
	// Wire up Submit btn
	$('#btn-ok').on('click',function() {
		// Do validation
		if ($('#form_shipment').valid()){
			// Submit to wp-ajax.
			nav_loading_show();
			$('#btn-ok,#btn-draft').attr("disabled", true);
			// Submit to wp-ajax.
			$('#form_shipment').ajaxSubmit({ 
				url : cpwebservice_save_shipment.ajaxurl + '?action=cpwebservice_save_shipment',
				data: { '_wpnonce' : cpwebservice_save_shipment.postNonce, 
					'package_index' : $('#shipment_data').data('packageindex'), 'order_id' : $('#shipment_data').data('orderid') },
				type : 'POST',
				success: function(data) { 
					nav_loading_hide();
					// Refresh order action rows
					parent.cpwebservice_order_refresh();
					// If true, refresh modal.
					 if (data=='true'){
						 $('#display_message').html(cpwebservice_save_shipment.success + ': ' + data);
						 add_success_icon();
						 // Refresh Modal.
						 window.location.reload(true);
					 } else {
						 $('#display_message').html(data);
						 $('#btn-ok,#btn-draft').removeAttr("disabled");
						 add_warning_icon();
					 }
		        }
			});
		} else {
			// Display Message.
			$('#display_message').html(cpwebservice_save_shipment.validation);
		}
	});
	
	var cpwebservice_shipment_summary = function() {
		// Save draft first, then refresh.
		cpwebservice_shipment_savedraft(function() {
			$.ajax({ url : cpwebservice_create_shipment_summary.ajaxurl + '?action=cpwebservice_create_shipment_summary&'+$.param({ '_wpnonce' : cpwebservice_create_shipment.postNonce, 'package_index' : $('#shipment_data').data('packageindex'), 'order_id' : $('#shipment_data').data('orderid') }),
			success : function(data) {
					$('#shipment_summary').html(data);
			} 
			});
		});
		return false;
	};
	$('#shipment_summary_refresh').on('click', cpwebservice_shipment_summary);
	$('#sender_address,#selected_service').on('change', cpwebservice_shipment_summary);
	
	var updateSummary = null;
	$('.package_details').on('input', function() {
		if(updateSummary != null)
		{
		  clearTimeout(updateSummary); 
		  updateSummary = null;
		}
		else
		{
			updateSummary = window.setTimeout(cpwebservice_shipment_summary, 500);
		}
	})
	
	$('#sender_address').on('change', function() {
		// show/hide.
		$('.sender_address_display').hide();
		var index = $("#sender_address").prop('selectedIndex');
		$('#sender_address_display_'+index).show();
	});
	
	// Request refund button
	$('#btn-refund').on('click',function() {
		if (confirm(cpwebservice_shipment_refund.confirm)){
			// Submit to wp-ajax.
			nav_loading_show();
			$('#form_refund').ajaxSubmit({ 
				url : cpwebservice_shipment_refund.ajaxurl + '?action=cpwebservice_shipment_refund',
				data: { '_wpnonce' : cpwebservice_shipment_refund.postNonce, 
					'package_index' : $('#shipment_data').data('packageindex'), 'order_id' : $('#shipment_data').data('orderid') },
				type : 'POST',
				success: function(data) { 
					nav_loading_hide();
					// If true, refresh modal.
					 if (data=='true'){ 
						 $('#display_message').html(cpwebservice_shipment_refund.success + ': ' + data);
						 add_success_icon();
						 // Refresh Modal if success.
						 window.location.reload(true);
					} else {
						// Display error.
						$('#display_message').html(data);
						add_warning_icon();
					}
		        }
			});
			
		}
		return false;
	});
	
	// Auto height
	$('#create_shipment article').css('height',window.innerHeight - 62); // iframe padding etc: 50+50, 12.
	$(window).on('resize', function(){
		// Auto height
		jQuery('#create_shipment article').css('height',window.innerHeight - 62); // iframe padding etc: 50+50, 12.
	});
	
	var nav_loading_show = function() {
		$('.canadapost-nav-loading,.loading-action').show();
	};
	var nav_loading_hide = function() {
		$('.canadapost-nav-loading,.loading-action').hide();
	};
	var add_success_icon = function() {
		 $('#display_message').prepend('<img src="' + $('#success_icon').data('img') + '" border="0" style="vertical-align:middle" /> ');
	}
	var add_warning_icon = function() {
		 $('#display_message').prepend('<span class="dashicons dashicons-warning"></span>');
	}
	
	$('#customs_export').on('change', function() {
		$('#customs_export_other_display').toggle($('#customs_export').val() == 'OTH');
	});
	// init
	$('#customs_export_other_display').toggle($('#customs_export').val() == 'OTH');

    $('#customs_currency').on('change', function() {
		$('#customs_currency_conversion_display').toggle($('#customs_currency').val() != 'CAD');
	});
	// init
	$('#customs_currency_conversion_display').toggle($('#customs_currency').val() != 'CAD');
	
	$('#form_shipment').validate({
        highlight: function(element) {
            $(element).closest('.form-group').addClass('has-error');
        },
        unhighlight: function(element) {
            $(element).closest('.form-group').removeClass('has-error');
        },
        errorElement: 'span',
        errorClass: 'col-sm-offset-2 help-block',
        errorPlacement: function(error, element) {
            if(element.parent('.input-group').length) {
                error.insertAfter(element.parent());
            } else {
                error.insertAfter(element);
            }
        }
    });
	
	// Adding elements
	$('#btn_customs_items').click(function() {
		cpwebservice_add_elements('#customs_items','div.customs_item');
		return false;
	});
	// Remove element
	$('#customs_items').on('click', '.btn_custom_remove', function() {
		$(this).closest('.customs_item').remove();
	});
	
	$('#customs_items').on('change', '.origin-country', function() {
		var country = $(this);
		var prov = country.parent().next('.origin-prov');
		if(country.data('origincountry') == country.val()){ prov.show(); } else { prov.hide(); prov.find('select').val(''); }
	});
	
	$('.pickup-indicator').on('change', function() {
		$('.shipping-point-display').toggle($(this).val() == 'dropoff');
	});
	
	// Shipment Label page. Auto-add to Tracking.
	var autoUpdate = $('#cpwebservice_tracking_autoupdate');
	
	if (autoUpdate.length > 0 && autoUpdate.data('autosync') == true){		
		// Add Tracking 
		var pin = autoUpdate.data('trackingpin');
		parent.cpwebservice_updatetracking(pin);
	}
	
	// Shipment Label
	$('#shipment_label_pdf').css('height',window.innerHeight - 200); 
	
	// Dropdown toggle
	$('.dropdown-toggle').on('click', function(){
		 $(this).next('.dropdown-menu').toggle();
	});
	$('.dropdown-menu').on('mouseleave', function(){
		$(this).hide();
	});
	// Boxes
	$('.auto-box').on('click', function() {
		if (confirm(cpwebservice_create_shipment.confirm)){
			var box = $(this).data('box');
			if (box){
				$('#length').val(box.length);
				$('#width').val(box.width);
				$('#height').val(box.height);
				cpwebservice_shipment_summary();
			}
		}
		$(this).parent().parent().hide();
	});
	
	
	$('#templates').on('click', '.auto-template-add', function() {
		var name = prompt(cpwebservice_save_shipment_template.prompt + ':', cpwebservice_save_shipment_template.defaultname + ' ' + ($(this).data('index') + 1));
		if (name != null){
			if (name == ''){ name = cpwebservice_save_shipment_template.defaultname; }
			// Submit and save current data to wp-ajax.
			nav_loading_show();
			$('#form_shipment').ajaxSubmit({ 
				url : cpwebservice_save_shipment_template.ajaxurl + '?action=cpwebservice_save_shipment_template',
				data: { '_wpnonce' : cpwebservice_save_shipment_template.postNonce, 'name' : name },
				type : 'POST',
				dataType : 'json',
				success: function(data) {
					nav_loading_hide();
					 $('#display_message').html(cpwebservice_save_shipment_template.success + ': ' + data);
					 if (data == true) { 
						 add_success_icon();
						 // Refresh
						 cpwebservice_shipment_template_load();
						 // Update Index.
						 $('#templates .auto-template-add').data('index', $('#templates .auto-template-add').data('index') + 1 );
					 }
		        }
			});
		}
	});
	
	var html_encode = function(value){
		return $('<div/>').text(value).text();
	}
	
	var cpwebservice_shipment_template_load = function() {
		// Refresh/Load templates.
		$.ajax({ 
			url : cpwebservice_save_shipment_template.ajaxurl + '?action=cpwebservice_shipment_template_list',
			data: { '_wpnonce' : cpwebservice_save_shipment_template.postNonce },
			type : 'POST',
			dataType : 'json',
			success: function(data) {
				 //(re)populate dropdown.
				 var menu = $('#templates ul.dropdown-menu');
				 var menu_items =  $('#templates ul.dropdown-menu li').not(':first').remove(); // remove all but 1st item (the 'Add' link)
				 if (data != '' && $.isArray(data)){
					 $.each(data, function(index, item) {
						 	menu.append($('<li></li>')
						 	    .append($('<a href="#" class="auto-template"></a>').data('template', item).text(item.name).prepend('<span class="dashicons dashicons-welcome-write-blog"></span>'))
						 	    .append($('<a href="#" class="auto-template-remove"><span class="dashicons dashicons-no-alt"></span></a>').data('index', index))
						 	    );
/*						 menu.append('<li><a href="#" class="auto-template" data-template="'+ encodeURI(JSON.stringify(item.data))+'"> <span class="dashicons dashicons-welcome-write-blog"></span>' + 
								 html_encode(item.name) + '</a>' + '<a href="#" class="auto-template-remove" data-index="'+index+'"><span class="dashicons dashicons-no-alt"></span></a></li>'); */
					 });
				 }
	        }
		});
	};
	
	$('#templates').on('click', '.auto-template', function() {
		if (confirm(cpwebservice_save_shipment_template.confirm)){
			// apply selected template.
			var template = $(this).data('template');
			if (template.data){
				// Assign each field.
				if (template.data.shipment_type){ $('input[name="shipment_type"][value="'+template.data.shipment_type+'"]').prop('checked', true); }
				// Not customer data.
				//if (template.data.destination_email){ $('#destination_email').val(template.data.destination_email); }
				//if (template.data.contact_phone){ $('#contact_phone').val(template.data.contact_phone); }
				// end customer data.
				if (template.data.sender_address_index){ 
					var opt = $('#sender_address option[value="'+template.data.sender_address_index+'"]');
					if (opt.length > 0) { opt.prop('selected', true); }
				}
				if (template.data.pickup_indicator){ $('input[name="pickup_indicator"][value="'+template.data.pickup_indicator+'"]').prop('checked', true); }
				if (template.data.shipping_point_id){ $('#shipping_point_id').val(template.data.shipping_point_id); }
				
				// Method id.
				if (template.data.method_id){ 
					var opt = $('#selected_service option[value="'+template.data.method_id+'"]');
					if (opt.length > 0) { opt.prop('selected', true); }
				}
				if (template.data.package && cpwebservice_save_shipment_template.template_package == "1"){
					if (template.data.package.length){	$('input[name="length"]').val(template.data.package.length);	}
					if (template.data.package.width){	$('input[name="width"]').val(template.data.package.width);	}
					if (template.data.package.height){	$('input[name="height"]').val(template.data.package.height);	}
					if (template.data.package.weight){	$('input[name="weight"]').val(template.data.package.weight);	}
				}
				if (template.data.opt_signature){ $('input[name="opt_signature"]').prop('checked', template.data.opt_signature); }
				if (template.data.opt_delivery_confirmation){ $('input[name="opt_delivery_confirmation"]').prop('checked', template.data.opt_delivery_confirmation); }
				if (template.data.opt_packinginstructions){ $('input[name="opt_packinginstructions"]').prop('checked', template.data.opt_packinginstructions); }
				if (template.data.opt_postrate){ $('input[name="opt_postrate"]').prop('checked', template.data.opt_postrate); }
				if (template.data.opt_insuredvalue){ $('input[name="opt_insuredvalue"]').prop('checked', template.data.opt_insuredvalue); }
				if (template.data.opt_delivery_door){ 
					var opt = $('#opt_delivery_door option[value="'+template.data.opt_delivery_door+'"]'); if (opt.length > 0) { opt.prop('selected', true); }
				}
				if (template.data.opt_required){ 
					var opt = $('#opt_required option[value="'+template.data.opt_required+'"]'); if (opt.length > 0) { opt.prop('selected', true); }
				}
				if (template.data.opt_promocode){ $('#opt_promocode').val(template.data.opt_promocode); }
				if (template.data.insurance){ $('#insurance').val(template.data.insurance); }
				if (template.data.opt_outputformat){
					var opt = $('#opt_outputformat option[value="'+template.data.opt_outputformat+'"]'); if (opt.length > 0) { opt.prop('selected', true); }
				}
				// Customs values
				if(cpwebservice_save_shipment_template.template_customs == "1"){
					if (template.data.customs_licenseid){ $('#customs_licenseid').val(template.data.customs_licenseid); }
					if (template.data.customs_invoice){ $('#customs_invoice').val(template.data.customs_invoice); }
					if (template.data.customs_certificateid){ $('#customs_certificateid').val(template.data.customs_certificateid); }
					if (template.data.customs_nondelivery){  
						var opt = $('#customs_nondelivery option[value="'+template.data.customs_nondelivery+'"]'); 
						if (opt.length > 0) { opt.prop('selected', true); }
					}
					if (template.data.customs_export){  
						var opt = $('#customs_export option[value="'+template.data.customs_export+'"]'); 
						if (opt.length > 0) { opt.prop('selected', true); }
						$('#customs_export_other_display').toggle(template.data.customs_export == "OTH");
					}
					if (template.data.customs_export_other){
						$('#customs_export_other').val(template.data.customs_export_other);
					}
                    if (template.data.customs_currency_conversion){
						$('#customs_currency_conversion').val(template.data.customs_currency_conversion);
					}
					if (template.data.customs_currency){  
						var opt = $('#customs_currency option[value="'+template.data.customs_currency+'"]'); 
						if (opt.length > 0) { opt.prop('selected', true); }
                        $('#customs_currency_conversion_display').toggle(template.data.customs_currency != "CAD");
					}
				}
				
				if (template.data.payment_method){ 
					var opt = $('#payment_method option[value="'+template.data.payment_method+'"]');
					if (opt.length > 0) { opt.prop('selected', true); }
				}
				if (template.data.email_on_shipment){ $('input[name="email_on_shipment"]').prop('checked', template.data.email_on_shipment); }
				if (template.data.email_on_exception){ $('input[name="email_on_exception"]').prop('checked', template.data.email_on_exception); }
				if (template.data.email_on_delivery){ $('input[name="email_on_delivery"]').prop('checked', template.data.email_on_delivery); }
				// Apply changes.
				cpwebservice_shipment_summary();
			}
		}
	});
	$('#templates').on('click', '.auto-template-remove', function() {
		if (confirm(cpwebservice_save_shipment_template.remove)){
			// Post delete.
			var index = $(this).data('index');
			// Refresh/Load templates.
			$.ajax({ 
				url : cpwebservice_save_shipment_template.ajaxurl + '?action=cpwebservice_shipment_template_remove',
				data: { '_wpnonce' : cpwebservice_save_shipment_template.postNonce, 'index': index },
				type : 'POST',
				dataType : 'json',
				success: function(data) {
					 //(re)populate dropdown.
					cpwebservice_shipment_template_load();
		        }
			});
		}
	});
	
});


//Adds elements (by using the first element as a template)
function cpwebservice_add_elements(id,el) {
	var list = jQuery(id+' '+el);
	var i = list.size(); // one p tag.
	// Copy fields.
	var fields = list.first().clone(false);
	// clear the info in fields.
	fields.find('.btn_custom_remove').removeClass('hidden');
//	fields.children().each(function(){
//		var item = jQuery(this);
//		if (item.is('input[type=text],select')){ 
//			item.val(''); 
//		}
//		if (item.is('label')){ // checkbox/radio
//			item.children().prop('checked', false);
//		}
//		if (item.is('input[type=checkbox]')){ // checkbox/radio
//			item.prop('checked', false);
//		}
//	});
	jQuery(fields).appendTo(id);

}
