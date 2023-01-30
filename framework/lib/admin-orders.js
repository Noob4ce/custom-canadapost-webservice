"use strict";
/* Order Details */
jQuery( function ( $ ) {

	/* Shipments */
	
	// Remove btn.
	$('#cpwebservice_shipping_info').on('click', '.cpwebservice_shipment_remove', function(e) {
		e.preventDefault();
		var postUrl = this.href;
		if (confirm(cpwebservice_order_actions.confirm)){
			$.ajax({ url : postUrl, method : 'POST', data: { _wpnonce : cpwebservice_order_actions.removeNonce },
				success : function(data) {
					alert(data);
					window.cpwebservice_order_refresh();
				} 
			});
			
		}
		
		return false;
	});
    $('#cpwebservice_shipping_info').on('click', '.cpwebservice_package_remove', function(e) {
		e.preventDefault();
		var postUrl = this.href;
		if (confirm(cpwebservice_order_actions.confirm)){
			$.ajax({ url : postUrl, method : 'POST', data: { _wpnonce : cpwebservice_order_actions.removePackageNonce },
				success : function(data) {
					alert(data);
					window.cpwebservice_order_refresh();
				} 
			});
			
		}
		
		return false;
	});
	
	/* Tracking */
	$('.canadapost-tracking').on('click', function(event) {
		var pin = $('input#cpwebservice_trackingid').val();
		var url = $(this).attr('href') + pin;
		if (pin != ''){
			// ajax request.
			$('.cpwebservice_ajaxsave').show();
			$.get(url,function(data){
				if (data!='Duplicate Pin.') {
					$('#cpwebservice_tracking_result').append(data);
				}
				$('.cpwebservice_ajaxsave').hide();
			});
		}
		return false;
	});
	$('#cpwebservice_tracking_result').on('click','.cpwebservice_refresh',function() {
		var url = $(this).attr('href');
		var pin = $(this).data('pin');
		$('.cpwebservice_ajaxsave').show();
		$.get(url,function(data){
			$('#cpwebservice_tracking_result').find('.cpwebservice_track_'+pin).replaceWith(data);
			$('.cpwebservice_ajaxsave').hide();
		});
		return false;
	});
	$('#cpwebservice_tracking_result').on('click','.cpwebservice_remove',function() {
		if (confirm(cpwebservice_admin_orders.confirm)){
			var url = $(this).attr('href');
			var pin = $(this).data('pin');
			$('.cpwebservice_ajaxsave').show();
			$.get(url,function(data){
				$('#cpwebservice_tracking_result').find('.cpwebservice_track_'+pin).remove();
				$('.cpwebservice_ajaxsave').hide();
			});
		}
		return false;
	});
    $('.cpwebservice_admin_notice').on('click', '.notice-dismiss', function() {
         $.get(cpwebservice_order_actions.ajaxurl + '?action=cpwebservice_admin_notice_clear');
         return false;
    });
});

// Global methods
function cpwebservice_updatetracking(pin) {
	jQuery('input#cpwebservice_trackingid').val(pin);
	jQuery('.canadapost-tracking').trigger('click');
}
//Global method for refreshing order_actions
function cpwebservice_order_refresh(e) {
	jQuery.ajax({ url : cpwebservice_order_actions.ajaxurl + '?action=cpwebservice_order_actions&'+ jQuery.param({ '_wpnonce' : cpwebservice_order_actions.postNonce, 'order_id' : jQuery('#cpwebservice_shipping_info').data('orderid') }),
		success : function(data) {
				jQuery('#cpwebservice_order_actions').html(data);
				// Update Create Button
				var url = jQuery('.cpwebservice_createnew');
				if (url){
					jQuery('.cpwebservice_createnew_btn').attr('href', url.data('url'));
				}
		} 
	});
	return false;
};

