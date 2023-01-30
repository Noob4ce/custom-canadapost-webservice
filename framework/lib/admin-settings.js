"use strict";
/**
 *  Plugin Settings page
 */
jQuery( function($){
	
	// activates tabs
	$("#cpwebservice_tabs a").on('click', function(e) {
		e.preventDefault();
		cpwebservice_openpanel($(this).attr('href'), '#'+this.id);		
    });

	var cpwebservice_openpanel = function (id, link){
		$('.cpwebservice_panel').hide();
		var panel = $(id);
		if (panel.length > 0){
			panel.show();
		}
		$('#cpwebservice_tabs a.nav-tab-active').removeClass('nav-tab-active');
		$(link).addClass('nav-tab-active');
		location.hash = id+'_panel';
		
		return false;
	}
	
	// Display active tab.
	if (location.hash && location.hash.indexOf('cpwebservice') && location.hash.indexOf('_panel')){
		var id = location.hash.replace('_panel','');
		cpwebservice_openpanel(id, id+'_tab');
	}
	// update notice
	$( '#cpwebservice_update_notice' ).on('click', '.cpwebservice_update_notice_link', function(e){
			e.preventDefault();
			cpwebservice_openpanel('#cpwebservice_update', '#cpwebservice_update_tab');	
	});
	$( '.canadapost-notice-close' ).on( 'click', function( e ) {
		e.preventDefault();
        var dismiss_url = $(this).attr('href');
        // ajax request.
        $.post( dismiss_url, { } );
        $( '#cpwebservice_update_notice' ).hide();
    });
	
	// Settings Page
	$('.canadapost-mode').on('change', function() {
		var dev = false;
		$('.canadapost-mode').each(function(){
			dev = ($(this).val()=='dev');
			if (dev){ return false; } // break;
		});
		$('.woocommerce-canadapost-development-api').toggleClass('hidden',!dev);
	});
	$('.canadapost-mode-rates').on('change',function(){
		var dev = ($(this).val()=='dev');
		$('.canadapost-mode-rates-dev-msg').toggle(dev);
		$('.canadapost-mode-rates-live-msg').toggle(!dev);
	});
	$('.canadapost-mode-shipment').on('change',function(){
		var dev = ($(this).val()=='dev');
		$('.canadapost-mode-shipment-dev-msg').toggle(dev);
		$('.canadapost-mode-shipment-live-msg').toggle(!dev);
	});

	$('#woocommerce_cpwebservice_delivery_hide').on('click', function(){
		$('#woocommerce_cpwebservice_delivery').val(this.checked?'1':'');
	});
	
	$('#cpwebservice_address').on('change','.canadapost-postalcode-origin', function() {
		display_origin_postal();
	});
	var display_origin_postal = function() {
		var p = [];
		$('.canadapost-shipment-postal').each(function(){ 
			var i = $(this);
			var req = i.nextAll('.canadapost-postalcode-origin-label').children('input:checkbox');
			if (req.length > 0 && req[0].checked==true){
				p.push(i.val());
			}
		});
		// display
		$('.canadapost-postal-array').html((p.length > 0) ? p.join(',') : '');
		// if none:
		$('.canadapost-postal-requires-one').toggle((p.length == 0));
	};
	var display_geolocate_for_multiple = function() {
		$('.canadapost-display-geolocate').toggle($('div.cpwebservice_address_item').length > 1);
	};
	// Removing elements
	$('#cpwebservice_address').on('click', '.canadapost-address-remove', function() {
		if (confirm(cpwebservice_admin_settings.confirm)){ 
			jQuery(this).parent().parent('div').remove(); 
		}
		display_geolocate_for_multiple();
		display_origin_postal();
		return false;
	});
	// Adding elements
	$('#btn_cpwebservice_address').click(function() {
		cpwebservice_add_elements('#cpwebservice_address','div.cpwebservice_address_item');
		display_geolocate_for_multiple();
		display_origin_postal();
		return false;
	});
	
	// Validate Postal Code
	var validPostalCode = function(value, label) {
		var regex = /^[A-Za-z]{1}\d{1}[A-Za-z]{1} *\d{1}[A-Za-z]{1}\d{1}$/;
		$(label).toggle(regex.test(value) == false);
	}
	// Validate Zip Code
	var validZipCode = function(value, label) {
		var regex = /^([0-9]{5})(-[0-9]{4})?$/i; 
		$(label).toggle(regex.test(value) == false);
	}
	
	// Multiple postal codes now.
	//if ($('.canadapost-postal').val() != '') { validPostalCode($(this).val(), '.canadapost-postal-error'); }
	//$('#cpwebservice_settings').on('blur', '.canadapost-postal', function() { validPostalCode($(this).val(), '.canadapost-postal-error'); });

	$('.canadapost-shipment-postal').each(function(index, el) { 
		if ($(el).val() != '') { 
			if ($(el).data('postaltype') == 'zip'){
				validZipCode($(el).val(), '#'+el.id+'_error');
			} else {
				validPostalCode($(el).val(), '#'+el.id+'_error');
			}
		}
	});
	$('#cpwebservice_address').on('blur', '.canadapost-shipment-postal', function() { 
		if ($(this).data('postaltype') == 'zip'){
			validZipCode($(this).val(), '#'+this.id+'_error');
		} else {
			validPostalCode($(this).val(), '#'+this.id+'_error');
		}
		display_origin_postal();
	});
	
	// Handles Country dropdown changes to populate Prov/State dropdown.
	$('#cpwebservice_address').on('change', '.canadapost-shipment-country', function(e) { 
		var country = $(e.target).val();
		var prov = $(e.target).prevAll('.canadapost-shipment-prov');
		var provlist = [];
		if (country=='US'){
			provlist = $('#cpwebservice_lettermail_statearray').data('states');
		}
		if (country=='CA'){
			provlist = $('#cpwebservice_lettermail_provarray').data('provs');
		}
		prov.find('option').remove();
		prov.append('<option value=""></option>');
		// Populate.
		$.each(provlist,function(key, value) 
		{
			prov.append('<option value=' + key + '>' + value + '</option>');
		});
		// Display if more than 1 option (which is blank)
		var hasOptions = (prov.find('option').length > 1);
		prov.toggle(hasOptions);
		prov.prev('.canadapost-shipment-prov-label').toggle(hasOptions);
	});
	
	$('.woocommerce_cpwebservice_contractid_button').click(function(){
		$('#woocommerce_cpwebservice_contractid_display').toggle(this.value=="1");
		if (this.value=="0"){ $('#woocommerce_cpwebservice_contractid').val(''); }
	});

	// Validate Credentials Ajax
	$('.canadapost-validate').click(function(){
		var url = $(this).attr('href');
		var postvalues= { api_user:$('input#woocommerce_cpwebservice_api_user').val(),
				api_key:$('input#woocommerce_cpwebservice_api_key').val(),
				customerid:$('input#woocommerce_cpwebservice_account').val(),
				contractid:$('input#woocommerce_cpwebservice_contractid').val(),
				source_postalcode:$('#woocommerce_cpwebservice_shipment_postalcode0').val() };
		// ajax request.
		$('.cpwebservice_ajaxupdate').show();
		$.post(url,postvalues,function(data){
			//console.log('Data:'+data);
			$('#woocommerce_cpwebservice_validate p').html(data);
			$('#woocommerce_cpwebservice_validate').show();
		}).fail(function(jqXHR,textStatus,errorThrown){
			var data = 'Error: Please check your PHP Error log and ensure this plugins requirements are met (PHP5.4+, SimpleXML)' + '<br />' + errorThrown +
						'<br />' + (jqXHR.responseText != null ? jqXHR.responseText : '');
			$('#woocommerce_cpwebservice_validate p').html(data);
			$('#woocommerce_cpwebservice_validate').show();
			$('.cpwebservice_ajaxupdate').hide();
		})
		.done(function() { 
			$('.cpwebservice_ajaxupdate').hide();
		});
		return false;
	});
	
	// Validate DEV Credentials Ajax
	$('.canadapost-validate-dev').click(function(){
		var url = $(this).attr('href');
		var postvalues= { api_user:$('input#woocommerce_cpwebservice_api_dev_user').val(),
				api_key:$('input#woocommerce_cpwebservice_api_dev_key').val(),
				customerid:$('input#woocommerce_cpwebservice_account').val(),
				contractid:$('input#woocommerce_cpwebservice_contractid').val(),
				source_postalcode:$('#woocommerce_cpwebservice_shipment_postalcode0').val()  };
		// ajax request.
		$('.cpwebservice_ajaxupdate_dev').show();
		$.post(url,postvalues,function(data){
			//console.log('Data:'+data);
			$('#woocommerce_cpwebservice_validate_dev p').html(data);
			$('#woocommerce_cpwebservice_validate_dev').show();
		}).fail(function(jqXHR,textStatus,errorThrown){
			var data = 'Error: Please check your PHP Error log and ensure this plugins requirements are met (PHP5.4+, SimpleXML)' + '<br />' + errorThrown + 
					   '<br />' + (jqXHR.responseText != null ? jqXHR.responseText : '');
			$('#woocommerce_cpwebservice_validate_dev p').html(data);
			$('#woocommerce_cpwebservice_validate_dev').show();
			$('.cpwebservice_ajaxupdate').hide();
		})
		.done(function() { 
			$('.cpwebservice_ajaxupdate_dev').hide();
		});
		return false;
	}); 
	$('.canadapost-validate-close').on('click', function() {
		$(this).parent().hide();
		return false;
	});

	// Log Display Ajax
	$('.canadapost-log-display').click(function(){
		var url = $(this).attr('href');
		$('.canadapost-log-display-loading').show();
		$('#cpwebservice_log_display').hide();
		$('.canadapost-log-close').hide();
		$.get(url,function(data){
			//console.log('Data:'+data);
			$('#cpwebservice_log_display').html(data);
			$('#cpwebservice_log_display').slideDown();
			$('.canadapost-log-display-loading').hide();
			$('.canadapost-log-close').show();
		});
		return false;
	});
	// Shipment Log Display Ajax
	$('.canadapost-shipment-log-display').click(function(){
		var url = $(this).attr('href');
		$('.canadapost-shipment-log-display-loading').show();
		$('#cpwebservice_shipment_log_display').hide();
		$('.canadapost-shipment-log-close').hide();
		$.get(url,function(data){
			//console.log('Data:'+data);
			$('#cpwebservice_shipment_log_display').html(data);
			$('#cpwebservice_shipment_log_display').slideDown();
			$('.canadapost-shipment-log-display-loading').hide();
			$('.canadapost-shipment-log-close').show();
		});
		return false;
	});
	$('.canadapost-shipment-log-close').on('click', function() {
		$('#cpwebservice_shipment_log_display,.canadapost-shipment-log-close').hide();
	});
	$('.canadapost-log-close').on('click', function() {
		$('#cpwebservice_log_display,.canadapost-log-close').hide();
	});
	
	$('#btn_cpwebservice_boxes').click(function() {
		cpwebservice_add_elements('#cpwebservice_boxes','div.cpwebservice_boxes_item');
	});
	$('#cpwebservice_boxes').on('click','.cpwebservice_box_remove', function() {
		$(this).closest('div.cpwebservice_boxes_item').remove(); return false;
	});
	$('#btn_cpwebservice_lettermail').click(function() {
		cpwebservice_add_elements('#cpwebservice_lettermail','.cpwebservice_lettermail_item');
        $('#cpwebservice_lettermail .cpwebservice_lettermail_item').last().find('.cpwebservice_lettermail_country').val('CA').trigger('change');
        // re-activate select2.
        var fields = $('#cpwebservice_lettermail .cpwebservice_lettermail_item');
		cpwebservice_reactivate_select(fields, fields.length - 1);
	});
	$('#cpwebservice_lettermail').on('change', '.cpwebservice_lettermail_country', function(e) {
		var country = $(e.target).val();
		var prov = $(e.target).nextAll('.cpwebservice_lettermail_prov');
		var provlist = [];
		if (country=='US'){
			provlist = $('#cpwebservice_lettermail_statearray').data('states');
		}
		if (country=='CA'){
			provlist = $('#cpwebservice_lettermail_provarray').data('provs');
		}
		prov.find('option').remove();
		prov.append('<option value=""></option>');
		// Populate.
		$.each(provlist,function(key, value) 
		{
			prov.append('<option value=' + key + '>' + value + '</option>');
		});
		// Display if more than 1 option (which is blank)
		prov.toggle(prov.find('option').length > 1);
		
	});
	$('#cpwebservice_lettermail').on('click', '.cpwebservice_lettermail_remove', function(){
		$(this).closest('.cpwebservice_lettermail_item').remove(); return false;
	});
	
	// Service Options: Label and Margin
	$('.canadapost-service-label-edit').on('click', function() {
		var input = $(this).hide().nextAll('.canadapost-service-label-wrapper').show().children('input');
		if (input.val() == ''){
			input.val(input.prop('placeholder'));
		}
		return false;
	});
	$('.canadapost-service-label-remove').on('click', function() {
		$(this).prev('input').val('');
		$(this).parent().hide().prevAll('.canadapost-service-label-edit').show();
		return false;
	});
	$('.canadapost-service-margin-edit').on('click', function() {
		var input = $(this).hide().nextAll('.canadapost-service-margin-wrapper').show().children('input');
		return false;
	});
	$('.canadapost-service-margin-remove').on('click', function() {
		$(this).prev('input').val('');
		$(this).parent().hide().prevAll('.canadapost-service-margin-edit').show();
		return false;
	});

	// Shipping class rules
	$('#cpwebservice_class_rules').on('click', '.btn_cpwebservice_rules_clear', function(e) {
		var fields = $(e.target).parent().parent().find('select').each(function() { 
			$(this).find('option:selected').prop('selected', false); 
		});
		if (typeof(jQuery.fn.select2) != 'undefined') {
			fields.trigger('change');
		} else { // chosen
			fields.trigger('chosen:updated');
		}
		return false;
	});
	
	$('#btn_cpwebservice_add_rule').click(function() {
		var row = $('tr.cpwebservice_rules').first().clone(false);
		$('#cpwebservice_class_rules tbody').append(row);
        // re-activate select2.
        var fields = $('#cpwebservice_class_rules tr:last td');
        // update names.
        var index = $('#cpwebservice_class_rules tr').length - 1;
		cpwebservice_reactivate_select(fields, index);
	});
	
	$('#btn_cpwebservice_license').on('click', function() {
		var url = $(this).attr('href');
		if ($('input#woocommerce_cpwebservice_licenseid').val() != ''){
			var postvalues= { licenseid:$('input#woocommerce_cpwebservice_licenseid').val(),
					email:$('input#woocommerce_cpwebservice_license_email').val() };
			if (postvalues.licenseid != '')
			// ajax request.
			$('.cpwebservice_ajax_licenseupdate').show();
			$('#cpwebservice_update_error').hide();
			$.post(url,postvalues,function(data){
				//console.log('Data:'+data);
				if (data && !(/response_false/i.test(data))){
				$('#cpwebservice_update_display').html(data);
				$('#cpwebservice_update_display').show();
				$('#cpwebservice_update_form').hide();
				$( '#cpwebservice_update_notice' ).hide();
				} else {
					// error
					$('#cpwebservice_update_error').show();
				}
			})
			.done(function() { 
				$('.cpwebservice_ajax_licenseupdate').hide();
			});
		} else {
			$('input#woocommerce_cpwebservice_licenseid').addClass('invalid');
		}
		return false;
	});
	$('#cpwebservice_update_display').on('click','#btn_cpwebservice_license_refresh', function() {
		$('#cpwebservice_update_display').hide();
		$('#cpwebservice_update_form').show();
	});
	$('#btn_cpwebservice_license_refresh_cancel').on('click', function() {
		$('#cpwebservice_update_display').show();
		$('#cpwebservice_update_form').hide();
    });
    $('#cpwebservice_btn_tracking_migrate').on('click', function() {
        var url = $(this).attr('href');
        $('.cpwebservice_ajax_tracking_migrate').show();
        $.post(url,'',function(data){
            $('#cpwebservice_result_tracking_migrate').text(data);
        })
        .done(function() { 
            $('.cpwebservice_ajax_tracking_migrate').hide();
        });
        return false;
    });
	$('#cpwebservice_btn_shipping_migrate').on('click', function() {
        var url = $(this).attr('href');
        if ($('#cpwebservice_btn_shipping_migrate').data('continue')) {
            url += '&continue=' + $('#cpwebservice_btn_shipping_migrate').data('continue');
        }
        $('.cpwebservice_ajax_shipping_migrate').show();
        $.post(url,'',function(data){
            if (data.indexOf('Continue') === 0){
                window.setTimeout(function() { 
                    var numupdated = /[0-9]+/g;
                    $('#cpwebservice_btn_shipping_migrate').data('continue', data.match(numupdated)[0]);
                    $('#cpwebservice_btn_shipping_migrate').click(); 
                }, 100);
            } else {
                // complete
                $('#cpwebservice_btn_shipping_migrate').data('continue', 0);
            }
            $('#cpwebservice_result_shipping_migrate').text(data);
        })
        .done(function() { 
            $('.cpwebservice_ajax_shipping_migrate').hide();
        });
        return false;
    });
    $('.cpwebservice_tracking_template').on('change', function(e) { 
		var template = $(e.target).find(':selected').data('template');
		$('#cpwebservice_tracking_template_preview img').attr('src', template);
	});
    // On Load
    $('#cpwebservice_tracking_template_preview img').attr('src', $('.cpwebservice_tracking_template').find(':selected').data('template')).parent().show();
});


function cpwebservice_reactivate_select(fields, itemindex) {
    
    fields.children('select').each(function () {
        jQuery(this).attr('name', jQuery(this).attr('name').replace('[0]', '[' + itemindex + ']'));
    });
    if (typeof (jQuery.fn.select2) != 'undefined') {
        fields.find('.select2-chosen,.select2-container').remove();
        fields.children('select').select2();
    } else { // chosen
        fields.find('.chosen-container').remove();
        fields.children('select').chosen();
    }
}

// Adds elements (by using the first element as a template)
function cpwebservice_add_elements(id,el) {
	var list = jQuery(id+' '+el);
	var i = list.size(); // one p tag.
	// Copy fields.
	var fields = list.first().clone(false);
	// clear the info in fields.
	fields.children().each(function(){
		var item = jQuery(this);
		if (item.prop('id')){
			item.prop('id', item.prop('id').replace('0', i));
		}
		if (item.is('input[type=text],select')){ 
			item.val(''); 
		}
		if (item.is('label')){ // checkbox/radio
			item.children().prop('checked', false);
		}
		if (item.is('input[type=checkbox]')){ // checkbox/radio
			item.prop('checked', false);
		}
		if (item.hasClass('canadapost-remove-btn')){
			item.removeClass('hidden');
		}
		if (item.hasClass('canadapost-hide-new')){
			item.addClass('hidden');
		}
	});
	jQuery(fields).appendTo(id);

}

