// Require Postal Code.
jQuery(function($){
	$('.button[name="calc_shipping"]').on('click', function() {
		var postal = $('#calc_shipping_postcode');
		var ca = $('#calc_shipping_country');
		if (postal !=null && postal.val()=="" && ca !=null && (ca.val() == 'US' || ca.val() == 'CA')){
			$('#calc_shipping_postcode_required').removeClass('hidden').show();
			return false;
		} else {
			$('#calc_shipping_postcode_required').addClass('hidden').hide();
		}
	});
});