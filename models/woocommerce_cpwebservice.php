<?php
/*
 Main Canada Post Webserivce Class
 woocommerce_cpwebservice.php

Copyright (c) 2013-2022 Jamez Picard

*/
class woocommerce_cpwebservice extends cpwebservice_shippingmethod
{	
    protected $testing_rates;
	/*
	 * Displays for labels/text.
	 */
	public function get_resource($id) {
	    return cpwebservice_r::resource($id);
	}
	
	public function init_available_services() {
	    $this->available_services = array(
	        'DOM.RP'=>__('Regular Parcel', 'woocommerce-canadapost-webservice'),
	        'DOM.EP'=>__('Expedited Parcel', 'woocommerce-canadapost-webservice'),
	        'DOM.XP'=>__('Xpresspost', 'woocommerce-canadapost-webservice'),
	        'DOM.XP.CERT'=>__('Xpresspost Certified', 'woocommerce-canadapost-webservice'),
	        'DOM.PC'=>__('Priority', 'woocommerce-canadapost-webservice'),
	        'DOM.LIB'=>__('Library Books', 'woocommerce-canadapost-webservice'),
	        'USA.SP.AIR'=>__('Small Packet USA Air', 'woocommerce-canadapost-webservice'),
	        'USA.TP'=>__('Tracked Packet USA', 'woocommerce-canadapost-webservice'),
	        'USA.EP'=>__('Expedited Parcel USA', 'woocommerce-canadapost-webservice'),
	        'USA.XP'=>__('Xpresspost USA', 'woocommerce-canadapost-webservice'),
	        'USA.PW.ENV'=>__('Priority Worldwide Envelope USA', 'woocommerce-canadapost-webservice'),
	        'USA.PW.PAK'=>__('Priority Worldwide Pak USA', 'woocommerce-canadapost-webservice'),
	        'USA.PW.PARCEL'=>__('Priority Worldwide Parcel USA', 'woocommerce-canadapost-webservice'),
	        'INT.SP.SURF'=>__('Small Packet International Surface', 'woocommerce-canadapost-webservice'),
	        'INT.SP.AIR'=>__('Small Packet International Air', 'woocommerce-canadapost-webservice'),
	        'INT.IP.AIR'=>__('International Parcel Air', 'woocommerce-canadapost-webservice'),
	        'INT.IP.SURF'=>__('International Parcel Surface', 'woocommerce-canadapost-webservice'),
	        'INT.TP'=>__('Tracked Packet International', 'woocommerce-canadapost-webservice'),
	        'INT.XP'=>__('Xpresspost International', 'woocommerce-canadapost-webservice'),
	        'INT.PW.ENV'=>__('Priority Worldwide Envelope International', 'woocommerce-canadapost-webservice'),
	        'INT.PW.PAK'=>__('Priority Worldwide Pak International', 'woocommerce-canadapost-webservice'),
	        'INT.PW.PARCEL'=>__('Priority Worldwide parcel International', 'woocommerce-canadapost-webservice')
	    );
	    $this->service_descriptions = array(
	        'DOM.RP'=> __('Max', 'woocommerce-canadapost-webservice') . sprintf(': %s 200cm; %s + %s: 300cm; %s 30 kg', __('Length', 'woocommerce-canadapost-webservice'), __('Length', 'woocommerce-canadapost-webservice'), __('Girth', 'woocommerce-canadapost-webservice'), __('Weight', 'woocommerce-canadapost-webservice')),
 	        'DOM.EP'=> __('Max', 'woocommerce-canadapost-webservice') . sprintf(': %s 200cm; %s + %s: 300cm; %s 30 kg', __('Length', 'woocommerce-canadapost-webservice'), __('Length', 'woocommerce-canadapost-webservice'), __('Girth', 'woocommerce-canadapost-webservice'), __('Weight', 'woocommerce-canadapost-webservice')),
 	        'DOM.XP'=> __('Max', 'woocommerce-canadapost-webservice') . sprintf(': %s 200cm; %s + %s: 300cm; %s 30 kg', __('Length', 'woocommerce-canadapost-webservice'), __('Length', 'woocommerce-canadapost-webservice'), __('Girth', 'woocommerce-canadapost-webservice'), __('Weight', 'woocommerce-canadapost-webservice')),
// 	        'DOM.XP.CERT'=>__('Xpresspost Certified', 'woocommerce-canadapost-webservice'),
// 	        'DOM.PC'=> __('Max', 'woocommerce-canadapost-webservice') . sprintf(': %s 200cm; %s + %s: 300cm; %s 30 kg', __('Length', 'woocommerce-canadapost-webservice'), __('Length', 'woocommerce-canadapost-webservice'), __('Girth', 'woocommerce-canadapost-webservice'), __('Weight', 'woocommerce-canadapost-webservice')),
// 	        'DOM.LIB'=> __('Library Materials is a service accessible to recognized public libraries, university libraries, or other libraries maintained by non-profit organizations or associations and which are for public use in Canada', 'woocommerce-canadapost-webservice');
 	        'USA.SP.AIR'=> __('Max', 'woocommerce-canadapost-webservice') . sprintf(': %s 60cm;  (%s + %s + %s) < 90 cm; %s 1kg', __('Length', 'woocommerce-canadapost-webservice'), __('Length', 'woocommerce-canadapost-webservice'), __('Width', 'woocommerce-canadapost-webservice'), __('Height', 'woocommerce-canadapost-webservice'),  __('Weight', 'woocommerce-canadapost-webservice')),
 	        'USA.TP'=> __('Max', 'woocommerce-canadapost-webservice') . sprintf(': %s 60cm;  (%s + %s + %s) < 90 cm; %s 1kg', __('Length', 'woocommerce-canadapost-webservice'), __('Length', 'woocommerce-canadapost-webservice'), __('Width', 'woocommerce-canadapost-webservice'), __('Height', 'woocommerce-canadapost-webservice'),  __('Weight', 'woocommerce-canadapost-webservice')),
 	        'USA.EP'=> __('Max', 'woocommerce-canadapost-webservice') . sprintf(': %s 200cm; %s + %s: 274cm; %s 30 kg', __('Length', 'woocommerce-canadapost-webservice'), __('Length', 'woocommerce-canadapost-webservice'), __('Girth', 'woocommerce-canadapost-webservice'), __('Weight', 'woocommerce-canadapost-webservice')),
 	        'USA.XP'=> __('Max', 'woocommerce-canadapost-webservice') . sprintf(': %s 200cm; %s + %s: 274cm; %s 30 kg', __('Length', 'woocommerce-canadapost-webservice'), __('Length', 'woocommerce-canadapost-webservice'), __('Girth', 'woocommerce-canadapost-webservice'), __('Weight', 'woocommerce-canadapost-webservice')),
// 	        'USA.PW.ENV'=>__('Priority Worldwide Envelope USA', 'woocommerce-canadapost-webservice'),
// 	        'USA.PW.PAK'=>__('Priority Worldwide Pak USA', 'woocommerce-canadapost-webservice'),
 	        'USA.PW.PARCEL'=>__('Max', 'woocommerce-canadapost-webservice') . sprintf(': %s 200cm; %s + %s: 300cm; %s 30 kg', __('Length', 'woocommerce-canadapost-webservice'), __('Length', 'woocommerce-canadapost-webservice'), __('Girth', 'woocommerce-canadapost-webservice'), __('Weight', 'woocommerce-canadapost-webservice')),
 	        'INT.SP.SURF'=>__('Max', 'woocommerce-canadapost-webservice') . sprintf(': %s 60cm;  (%s + %s + %s) < 90 cm; %s 2kg', __('Length', 'woocommerce-canadapost-webservice'), __('Length', 'woocommerce-canadapost-webservice'), __('Width', 'woocommerce-canadapost-webservice'), __('Height', 'woocommerce-canadapost-webservice'),  __('Weight', 'woocommerce-canadapost-webservice')),
 	        'INT.SP.AIR'=> __('Max', 'woocommerce-canadapost-webservice') . sprintf(': %s 60cm;  (%s + %s + %s) < 90 cm; %s 2kg', __('Length', 'woocommerce-canadapost-webservice'), __('Length', 'woocommerce-canadapost-webservice'), __('Width', 'woocommerce-canadapost-webservice'), __('Height', 'woocommerce-canadapost-webservice'),  __('Weight', 'woocommerce-canadapost-webservice')),
 	        'INT.IP.AIR'=>__('Max', 'woocommerce-canadapost-webservice') . sprintf(': %s 150cm; %s + %s: 300cm; %s 30 kg', __('Length', 'woocommerce-canadapost-webservice'), __('Length', 'woocommerce-canadapost-webservice'), __('Girth', 'woocommerce-canadapost-webservice'), __('Weight', 'woocommerce-canadapost-webservice')),
 	        'INT.IP.SURF'=>__('Max', 'woocommerce-canadapost-webservice') . sprintf(': %s 150cm; %s + %s: 300cm; %s 30 kg', __('Length', 'woocommerce-canadapost-webservice'), __('Length', 'woocommerce-canadapost-webservice'), __('Girth', 'woocommerce-canadapost-webservice'), __('Weight', 'woocommerce-canadapost-webservice')),
 	        'INT.TP'=> __('Max', 'woocommerce-canadapost-webservice') . sprintf(': %s 60cm;  (%s + %s + %s) < 90 cm; %s 2kg', __('Length', 'woocommerce-canadapost-webservice'), __('Length', 'woocommerce-canadapost-webservice'), __('Width', 'woocommerce-canadapost-webservice'), __('Height', 'woocommerce-canadapost-webservice'),  __('Weight', 'woocommerce-canadapost-webservice')),
 	        'INT.XP'=>__('Max', 'woocommerce-canadapost-webservice') . sprintf(': %s 150cm; %s + %s: 300cm; %s 30 kg', __('Length', 'woocommerce-canadapost-webservice'), __('Length', 'woocommerce-canadapost-webservice'), __('Girth', 'woocommerce-canadapost-webservice'), __('Weight', 'woocommerce-canadapost-webservice')),
// 	        'INT.PW.ENV'=>__('Priority Worldwide Envelope International', 'woocommerce-canadapost-webservice'),
// 	        'INT.PW.PAK'=>__('Priority Worldwide Pak International', 'woocommerce-canadapost-webservice'),
 	        'INT.PW.PARCEL'=>__('Max', 'woocommerce-canadapost-webservice') . sprintf(': %s 200cm; %s + %s: 300cm; %s 30 kg', __('Length', 'woocommerce-canadapost-webservice'), __('Length', 'woocommerce-canadapost-webservice'), __('Girth', 'woocommerce-canadapost-webservice'), __('Weight', 'woocommerce-canadapost-webservice'))
	    );
	}
	
	
	/*
     * Return destination Label (ie. Canada, USA, International) from Service code.
    */
	public function get_destination_from_service($service_code){
	    if (!empty($service_code) && strlen($service_code) >= 3) {
	        switch(substr($service_code,0,3)) {
	            case 'DOM': return __('Canada', 'woocommerce-canadapost-webservice');
	            case 'USA': return __('USA', 'woocommerce-canadapost-webservice');
	            case 'INT': return __('International', 'woocommerce-canadapost-webservice');
	        }
	    }
	    return '';
	}
	
	/*
     * Return 2-char Country Code (CA, US, ZZ) ZZ is international from Service code.
    */
	public function get_destination_country_code_from_service($service_code){
	    if (!empty($service_code) && strlen($service_code) >= 3) {
	        switch(substr($service_code,0,3)) {
	            case 'DOM': return 'CA';
	            case 'USA': return 'US';
	            case 'INT': return 'ZZ';
	        }
	    }
	    return '';
	}
	
	
	

	/*
	 * Canada Post API rates lookup function
	 */
	public function get_rates($dest_country, $dest_state, $dest_city, $dest_postal_code, $weight_kg, $length, $width, $height, $services = array(), $add_options = null, $price_details = null) {

		$rates = array();

		$username = ($this->options->mode=='live') ? $this->options->api_user : $this->options->api_dev_user;
		$password = ($this->options->mode=='live') ? $this->options->api_key  : $this->options->api_dev_key;

		// REST URL
		$service_url = ($this->options->mode=='live') ? 'https://soa-gw.canadapost.ca/rs/ship/price' : 'https://ct.soa-gw.canadapost.ca/rs/ship/price'; // dev.  prod:

		// Has Services flag (Services are enabled for this country)
		$has_services = false;
		
		// if $services param is set, only request services within this array.
		$limit_services = (!empty($services) && is_array($services) && count($services) > 0);

		$xmlRequest =  new SimpleXMLElement(<<<XML
<?xml version="1.0" encoding="UTF-8"?>
<mailing-scenario xmlns="http://www.canadapost.ca/ws/ship/rate-v3">
  <customer-number></customer-number>
  <parcel-characteristics>
    <weight></weight>
    <dimensions><length></length><width></width><height></height></dimensions>
  </parcel-characteristics>
  <services></services>
  <expected-mailing-date></expected-mailing-date>
  <origin-postal-code></origin-postal-code>
  <destination>
  </destination>
</mailing-scenario>
XML
		);

		// Create GetRates request xml
		$xmlRequest->{'origin-postal-code'} = $this->options->source_postalcode;
		$postalCode = str_replace(' ','',strtoupper($dest_postal_code)); //N0N0N0 (no spaces, uppercase)
		if ($dest_country == 'CA'){ $postalCode = str_replace('-', '', $postalCode); }

		// Customer Number for venture one rates.
		if (!empty($this->options->account)){
			$xmlRequest->{'customer-number'} = $this->options->account;
			// Add Contract Id if entered.
			if (!empty($this->options->contractid)){
				$xmlRequest->addChild('contract-id', $this->options->contractid);
			}
		} else {
			// use public rates
			unset($xmlRequest->{'customer-number'});
			$xmlRequest->addChild('quote-type', 'counter');
		}


		// Add lead times.
		$lead_time = null;
		if (!empty($this->options->delivery)){
			$processTime = DAY_IN_SECONDS * floatval($this->options->delivery); // in seconds
			$lead_time = date('Y-m-d', current_time('timestamp') + $processTime);
			$xmlRequest->{'expected-mailing-date'} = $lead_time; 
		} else {
			unset($xmlRequest->{'expected-mailing-date'}); // remove element
		}

		// Parcel Dimensions
		if ($length > 0 || $width > 0 || $height > 0){

			// Round to 1 decimal place.
			$length = number_format(floatval($length),1,'.',''); $width = number_format(floatval($width),1,'.',''); $height = number_format(floatval($height),1,'.','');
				
			$xmlRequest->{'parcel-characteristics'}->dimensions->length = $length;
			$xmlRequest->{'parcel-characteristics'}->dimensions->width = $width;
			$xmlRequest->{'parcel-characteristics'}->dimensions->height = $height;
				
			//$dimensions = "<dimensions><length>{$length}</length><width>{$width}</width><height>{$height}</height></dimensions>";
		} else {
			unset($xmlRequest->{'parcel-characteristics'}->dimensions); // remove element
		}

		if ($dest_country == 'CA')
			// Canada
		{
			$xmlRequest->destination->addChild('domestic','');
			$xmlRequest->destination->domestic->addChild('postal-code', $postalCode);
			//$destination = "<domestic><postal-code>{$postalCode}</postal-code></domestic>";
				
			// Add Services
			foreach($this->services as $service_code){
				if (!empty($service_code) && strlen($service_code) >= 3 && substr($service_code,0,3) == 'DOM') {
					if (!$limit_services || in_array($service_code, $services)){
						$xmlRequest->services->addChild('service-code', $service_code);
						//$services .= "<service-code>$service_code</service-code>";
						$has_services = true;
					}
				}
			}
				
		} else if ($dest_country == 'US')
			// USA
		{
			$xmlRequest->destination->addChild('united-states','');
			$xmlRequest->destination->{'united-states'}->addChild('zip-code', $postalCode);
			//$destination = "<united-states><zip-code>{$postalCode}</zip-code></united-states>";
			// Add Services //$services = "<services><service-code>USA.EP</service-code><service-code>USA.SP.AIR</service-code><service-code>USA.XP</service-code></services>";
			foreach($this->services as $service_code){
				if (!empty($service_code) && strlen($service_code) >= 3 && substr($service_code,0,3) == 'USA') {
					if (!$limit_services || in_array($service_code, $services)){
						$xmlRequest->services->addChild('service-code', $service_code);
						$has_services = true;
					}
				}
			}
				
		} else
			// International
		{
			$xmlRequest->destination->addChild('international','');
			$xmlRequest->destination->international->addChild('country-code', $dest_country);
			//$destination = "<international><country-code>{$dest_country}</country-code></international>";
			// Add Services // $services = "<services><service-code>INT.XP</service-code><service-code>INT.IP.AIR</service-code><service-code>INT.SP.AIR</service-code></services>";
			foreach($this->services as $service_code){
				if (!empty($service_code) && strlen($service_code) >= 3 && substr($service_code,0,3) == 'INT') {
					if (!$limit_services || in_array($service_code, $services)){
						$xmlRequest->services->addChild('service-code', $service_code);
						$has_services = true;
					}
				}
			}
		}

		// Total Weight
		$xmlRequest->{'parcel-characteristics'}->weight = number_format( $weight_kg, 2, '.', '' ); // 2 decimal places : 99.99 format

		// Additional options (Signature Required, Insurance/Coverage)
		if (!empty($add_options) && (isset($add_options->signature) || (isset($add_options->insurance) && $add_options->insurance > 0))){
		    $xmlRequest->addChild('options', '');
		    if (isset($add_options->signature) && $add_options->signature){
		        $xml_signature = $xmlRequest->options->addChild('option', '');
		        $xml_signature->addChild('option-code', 'SO'); // Signature Option
		    }
		    if (isset($add_options->insurance) && $add_options->insurance > 0){
		        $xml_coverage = $xmlRequest->options->addChild('option', '');
		        $xml_coverage->addChild('option-code', 'COV'); // Coverage/Insurance
		        $xml_coverage->addChild('option-amount', number_format($add_options->insurance, 2, '.', '' )); // 2 decimal places : 99.99 format
		    }
		}

		// Service Language: (English or French) sent as Accept-language header with a value of 'fr-CA' or 'en-CA'
		// If using WPML:
		if (defined('ICL_LANGUAGE_CODE')){
			$service_language = (ICL_LANGUAGE_CODE=='fr') ? 'fr-CA':'en-CA'; // 'en-CA' is default
		} else if (get_locale() == 'fr_FR' || get_locale() == 'fr_CA'){
			$service_language = 'fr-CA';
		} else {
			$service_language = 'en-CA';
		}
		$service_language = apply_filters('cpwebservice_service_language', $service_language);
		 
		$is_international = ($dest_country != 'US' && $dest_country != 'CA');
		
		// Zip/PostalCode Required.
		if ($this->options->display_required_notice && $has_services && empty($postalCode) && !$is_international && isset($_POST['calc_shipping_country'])) {
			if ( function_exists('wc_add_notice') ){  // 2.1 and higher
				wc_add_notice(__( 'Zip or Postal Code is required to calculate shipping.', 'woocommerce-canadapost-webservice' ), 'notice' );
			} else { // 2.0
				global $woocommerce;
				$woocommerce->add_message(__( 'Zip or Postal Code is required to calculate shipping.', 'woocommerce-canadapost-webservice' ) );
			}
		}
		 
		if ($has_services && ( !empty($username) && !empty($password) ) && (!empty($postalCode) || $is_international)){ // Postal code cannot be empty for CA or US.
			try {
			    
			    $request_args = array(
			        'method' => 'POST',
			        'httpversion' => apply_filters( 'http_request_version', '1.1' ),
			        'headers' => array( 'Accept' => 'application/vnd.cpc.ship.rate-v3+xml',
			            'Content-Type' => 'application/vnd.cpc.ship.rate-v3+xml',
			            'Accept-language' => $service_language,
			            'Authorization' => 'Basic ' . base64_encode( $username . ':' . $password ) ),
			        'body' => $xmlRequest->asXML(),
			        'sslverify' => true,
			        'sslcertificates' => ABSPATH . WPINC . '/certificates/ca-bundle.crt'
			    );
			    $response = wp_remote_request($service_url, $request_args);
			    	
			    if ( is_wp_error( $response ) ) {
			        if ($this->options->log_enable){
			             $this->log->request['http'] = 'Failed. Error: ' . $response->get_error_code() . ": " . $response->get_error_message() . "\n";
			        }
			       return;
			    }
			    
			    // Retrieve http body
			    $http_response = wp_remote_retrieve_body( $response );

				// Using SimpleXML to parse xml response
				libxml_use_internal_errors(true);
				$xml = simplexml_load_string('<root>' . preg_replace('/<\?xml.*\?>/','',$http_response) . '</root>');
				if (!$xml && ($this->options->log_enable)) {
					$errmsg = 'Failed loading XML' . "\n";
					$errmsg .= $http_response . "\n";
					foreach(libxml_get_errors() as $error) {
						$errmsg.= "\t" . $error->message;
					}
					$this->log->request['errmsg'] = $errmsg;
				} else {
					if ($xml->{'price-quotes'} ) {
						$priceQuotes = $xml->{'price-quotes'}->children('http://www.canadapost.ca/ws/ship/rate-v3');
						if ( $priceQuotes->{'price-quote'} ) {
							foreach ( $priceQuotes as $priceQuote ) {
								$rate = new stdClass();
								$rate->service_code = (string) $priceQuote->{'service-code'};
								$rate->service =  $this->service_name_label($rate->service_code, (string)$priceQuote->{'service-name'});
								$rate->price = number_format(floatval((string)$priceQuote->{'price-details'}->{'due'}),2,'.','');
								$rate->cubed = (string)$priceQuote->{'weight-details'}->{'cubed-weight'};
								$rate->guaranteed =(string)$priceQuote->{'service-standard'}->{'guaranteed-delivery'}; $rate->guaranteed = ($rate->guaranteed=='true');
								$rate->expected_delivery = $this->format_expected_delivery((string) $priceQuote->{'service-standard'}->{'expected-delivery-date'});
								$rate->expected_mailing_date = $lead_time;
								if ($price_details){
								    $price_data = array();
								    $price_data['base'] = number_format(floatval((string)$priceQuote->{'price-details'}->{'base'}),2,'.','');
								    // Taxes
								    if (isset($priceQuote->{'price-details'}->taxes)) {
								        $price_data['taxes_gst'] =  isset($priceQuote->{'price-details'}->taxes->gst) ? number_format(floatval((string)$priceQuote->{'price-details'}->taxes->gst),2,'.','') : 0;
								        $price_data['taxes_pst'] =  isset($priceQuote->{'price-details'}->taxes->pst) ? number_format(floatval((string)$priceQuote->{'price-details'}->taxes->pst),2,'.','') : 0;
								        $price_data['taxes_hst'] =  isset($priceQuote->{'price-details'}->taxes->hst) ? number_format(floatval((string)$priceQuote->{'price-details'}->taxes->hst),2,'.','') : 0;
								    }
								    // options
								    if (isset($priceQuote->{'price-details'}->options)) {
								        $price_data['options'] = array();
								        $pr_options = $priceQuote->{'price-details'}->options->children();
								        foreach ( $pr_options as $pr_option ) {
								            $price_data['options'][] = array('code'=>(string)$pr_option->{'option-code'}, 'name'=>(string)$pr_option->{'option-name'}, 'price'=>(string)$pr_option->{'option-price'});
								        }
								    }
								    // adjustments
								    if (isset($priceQuote->{'price-details'}->adjustments)) {
								        $price_data['adjustments'] = array();
								        $pr_adjustments = $priceQuote->{'price-details'}->adjustments->children();
								        foreach ( $pr_adjustments as $pr_adjustment ) {
								            $price_data['adjustments'][] = array('code'=>(string)$pr_adjustment->{'adjustment-code'}, 'name'=>(string)$pr_adjustment->{'adjustment-name'}, 'price'=>(string)$pr_adjustment->{'adjustment-cost'}, 'qualifier'=>($pr_adjustment->qualifier && $pr_adjustment->qualifier->percent) ? (string)$pr_adjustment->qualifier->percent : '');
								        }
								    }
								    $rate->price_details = $price_data;
								}
								// Add rate
								$rates[] = $rate;

								if ($this->options->log_enable) {
									$this->log->request['service'] .= "\nService: " . $priceQuote->{'service-name'} . ($rate->service != $priceQuote->{'service-name'} ? ' ('.$rate->service .')' : '') . "\n";
									$this->log->request['service'] .= 'Price: ' . $priceQuote->{'price-details'}->{'due'} . "\n";
									$this->log->request['service'] .= 'Expected Mailing Date: ' . $lead_time . "\n";
									$this->log->request['service'] .= 'Guaranteed Delivery: ' . $priceQuote->{'service-standard'}->{'guaranteed-delivery'} . "\n";
									$this->log->request['service'] .= 'Expected Delivery: ' . $priceQuote->{'service-standard'}->{'expected-delivery-date'} . "\n\n";
								}
							}
							if (isset($this->testing_rates) && $this->testing_rates && isset($this->log->request['service'])){ echo str_replace("\n","<br />",esc_html($this->log->request['service'])); }
						}
					}
					if ($xml->{'messages'} && ($this->options->log_enable)) {
						$apierror = '';
						$messages = $xml->{'messages'}->children('http://www.canadapost.ca/ws/messages');
						foreach ( $messages as $message ) {
							$apierror .= 'Code: ' . $message->code . "\n";
							$apierror .= 'Msg: ' . $message->description . "\n\n";
						}
						$this->log->request['apierror'] = $apierror;
					}
				}
				if (isset($this->testing_rates) && $this->testing_rates && isset($this->log->request['apierror'])){ echo esc_html($this->log->request['apierror']); }
				
			} catch (Exception $ex) {
				// Http request went wrong.
				if ($this->options->log_enable){
					$this->log->request['error'] = 'Error: ' . $ex;
				}
				if (isset($this->testing_rates) && $this->testing_rates && isset($this->log->request['error'])){ echo esc_html($this->log->request['error']); }
			}
		} // endif $has_services
        else 
        {
            $this->log->request['service'] = "No services were available for the destination address. Look at enabled Services (ie. Xpresspost, etc) or Class rules.";
        }
		
		return $rates;
	}
	
	
	/*
	 * Do verification lookup with service. This method outputs the info.
	 */
	public function call_validate_api_credentials($customerid,$contractid,$api_user,$api_key,$source_postalcode, $mode)
	{
	    // Check API.
	    $username = $this->options->api_user = $api_user;
	    $password = $this->options->api_key = $api_key;
	    $this->options->account = $customerid;
	    $this->options->contractid = $contractid;
	    $this->options->source_postalcode = $source_postalcode;
	    $this->testing_rates = true;
	
	    $apiValid = false;
	    $message = "";
	    
	    // Change mode (temporarily) so that rates also operate in the same mode.
	    $this->options->mode=$mode;
	
	    // REST URL  (Get Service Info)
	    $service_url = ($this->options->mode=='live') ? 'https://soa-gw.canadapost.ca/rs/ship/service/DOM.EP?country=CA' : 'https://ct.soa-gw.canadapost.ca/rs/ship/service/DOM.EP?country=CA';
	
	    // If using WPML:
	    if (defined('ICL_LANGUAGE_CODE')){
	        $service_language = (ICL_LANGUAGE_CODE=='fr') ? 'fr-CA':'en-CA'; // 'en-CA' is default
	    } else if (get_locale() == 'fr_FR' || get_locale() == 'fr_CA'){
	        $service_language = 'fr-CA';
	    } else {
	        $service_language = 'en-CA';
	    }
	    $service_language = apply_filters('cpwebservice_service_language', $service_language);
	
	    echo ($this->options->mode=='live') ? esc_html__('Production/Live Server', 'woocommerce-canadapost-webservice') . ' :' : esc_html__('Development Server', 'woocommerce-canadapost-webservice') . ' :';
	
	    if (!empty($username) && !empty($password)){
	        try {
	
	            $request_args = array(
	                'method' => 'GET',
	                'httpversion' => apply_filters( 'http_request_version', '1.1' ),
	                'headers' => array( 'Accept' => 'application/vnd.cpc.ship.rate-v3+xml',
	                    'Accept-language' => $service_language,
	                    'Authorization' => 'Basic ' . base64_encode( $username . ':' . $password ) ),
	                'body' => null,
	                'sslverify' => true,
	                'sslcertificates' => ABSPATH . WPINC . '/certificates/ca-bundle.crt'
	            );
	            $response = wp_remote_request($service_url, $request_args);
	             
	            if ( is_wp_error( $response ) ) {
	                 
	                $message .= 'Failed. Error: ' . $response->get_error_code() . ": " . $response->get_error_message() . "\n";
	            }
	            else
	            {
	                // Retrieve http body
	                $http_response = wp_remote_retrieve_body( $response );
	                 
	                // Using SimpleXML to parse xml response
	                libxml_use_internal_errors(true);
	                $xml = simplexml_load_string('<root>' . preg_replace('/<\?xml.*\?>/','',$http_response) . '</root>');
	                if (!$xml) {
	                    $errmsg = 'Failed loading XML' . "\n";
	                    $errmsg .= $http_response . "\n";
	                    foreach(libxml_get_errors() as $error) {
	                        $errmsg.= "\t" . $error->message;
	                    }
	                    $message .= $errmsg;
	                } else {
	                    if ($xml->{'service'} ) {
	                        // Success! API correctly responded.
	                        $apiValid = true;
	                    }
	                    if ($xml->{'messages'}) {
	                        $apierror = '';
	                        $messages = $xml->{'messages'}->children('http://www.canadapost.ca/ws/messages');
	                        foreach ( $messages as $message ) {
	                            $apierror .= 'Error Code: ' . $message->code . "\n";
	                            $apierror .= 'Error Msg: ' . $message->description . "\n\n";
	                        }
	                        $message .= $apierror;
	                    }
	                }
	            }
	
	        } catch (Exception $ex) {
	            // Http request went wrong..
	            $message .= 'Error: ' . $ex . "\n";
	        }
	    }
	
	    echo str_replace("\n","<br />",esc_html($message));
	
	    if ($apiValid) {
	        echo '<strong>'.esc_html__('Success','woocommerce-canadapost-webservice').'!</strong> '.esc_html__('API Credentials validated with Canada Post','woocommerce-canadapost-webservice').'.';
	    } else {
	        echo '<strong>'.esc_html__('Failed','woocommerce-canadapost-webservice').'</strong> '.esc_html__('API Credentials did not validate','woocommerce-canadapost-webservice').'.';
		}
		
		// Api Limit: Wait 250ms before next lookup.
		usleep(250);
	
	    // Try get_rates to see if customer info works.
	    if ($apiValid) {
	        echo '<br /><strong>'. esc_html__('Testing Rates Lookup','woocommerce-canadapost-webservice').':</strong><br />';
	        $this->options->log_enable = true;
	        $rates = $this->get_rates('CA','ON','Ottawa','K1A 0B1',0.5,5,5,2); // Ship 5x5x2cm package to CP headquarters, Ottawa.
	        if (is_array($rates) && !empty($rates)) {
	            echo '<br /><strong>Rates Lookup Success!</strong> CustomerID/Venture One information appears to be valid and able to look up rates with Canada Post.';
	        } else {
	            echo '<br /><strong>Rates Lookup Failed</strong> Unable to look up rates. Please save settings before running credential validation. CustomerID/Venture One account number may be invalid or inactive.';
	        }
	    }
	}
}