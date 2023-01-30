<?php
/*
 Shipments (Order Details page)
woocommerce_cpwebservice_shipments.php

Copyright (c) 2013-2022 Jamez Picard

*/
class woocommerce_cpwebservice_shipments extends cpwebservice_shipments
{	
    
    public function get_resource($id) {
        return cpwebservice_r::resource($id);
    }
	
    /*
     * API function for Creating non-contract shipments
     */
	public function create_nc_shipment($shipment, $order, $package_index, $sender) {
	    
	    // Resulting label will be stored in shipment object.
	    $shipment['label'] = new stdClass(); 
	    $shipment['label']->links = array();
	    
	    $xmlRequest = new SimpleXMLElement(<<<XML
<?xml version="1.0" encoding="UTF-8"?>
<non-contract-shipment xmlns="http://www.canadapost.ca/ws/ncshipment-v4">
	<requested-shipping-point></requested-shipping-point>
	<delivery-spec>
		<service-code></service-code>
		<sender>
	        <name></name>
	        <company></company>
	        <contact-phone></contact-phone>
			<address-details>
				<address-line-1></address-line-1>
				<city></city>
				<prov-state></prov-state>
				<postal-zip-code></postal-zip-code>
			</address-details>
		</sender>
		<destination>
			<name></name>
			<address-details>
				<address-line-1></address-line-1>
				<city></city>
				<prov-state></prov-state>
				<country-code></country-code>
				<postal-zip-code></postal-zip-code>
			</address-details>
		</destination>
		<parcel-characteristics>
			<weight></weight>
			<dimensions>
				<length></length>
				<width></width>
				<height></height>
			</dimensions>
		</parcel-characteristics>
		<preferences>
			<show-packing-instructions>true</show-packing-instructions>
		</preferences>
		<references>
			<customer-ref-1></customer-ref-1>
			<customer-ref-2></customer-ref-2>
	    </references>
	</delivery-spec>
</non-contract-shipment>
XML
	    );
	    
	    
	    // Provide data to create shipment request xml
	    $index = intval($shipment['sender_address_index']);
	    $shipping_postcode = str_replace(' ','',strtoupper($this->order_prop($order['order'],'shipping_postcode'))); //N0N0N0 (no spaces, uppercase)
	    $this->shipment_address[$index]['postalcode'] = str_replace(' ','',strtoupper($this->shipment_address[$index]['postalcode'])); //N0N0N0 (no spaces, uppercase)
	    $xmlRequest->{'requested-shipping-point'} = $this->shipment_address[$index]['postalcode'];
	    // Set Service code. 
	    $xmlRequest->{'delivery-spec'}->{'service-code'} = $shipment['method_id'];
	    // Sender (Canadian address)
	    $xmlRequest->{'delivery-spec'}->sender->name = $this->truncate($this->shipment_address[$index]['contact'], 44);
	    if (!empty($this->shipment_address[$index]['contact'])){
	      $xmlRequest->{'delivery-spec'}->sender->company = $this->truncate($this->shipment_address[$index]['contact'], 44);
	    }
	    $xmlRequest->{'delivery-spec'}->sender->{'contact-phone'} = $this->truncate($this->shipment_address[$index]['phone'], 25);
	    $xmlRequest->{'delivery-spec'}->sender->{'address-details'}->{'address-line-1'} = $this->truncate($this->shipment_address[$index]['address'], 44);
	    if (!empty($this->shipment_address[$index]['address2'])){
	       $xmlRequest->{'delivery-spec'}->sender->{'address-details'}->addChild('address-line-2', $this->truncate($this->shipment_address[$index]['address2'], 44));
	    }
	    $xmlRequest->{'delivery-spec'}->sender->{'address-details'}->{'city'} = $this->truncate($this->shipment_address[$index]['city'], 40);
	    $xmlRequest->{'delivery-spec'}->sender->{'address-details'}->{'prov-state'} = $this->shipment_address[$index]['prov'];
	    $xmlRequest->{'delivery-spec'}->sender->{'address-details'}->{'postal-zip-code'} = $this->shipment_address[$index]['postalcode'];
	    // Destination
	    $xmlRequest->{'delivery-spec'}->destination->name = $this->order_prop($order['order'],'shipping_first_name') .' '. $this->order_prop($order['order'],'shipping_last_name');
	    $shipping_company= $this->order_prop($order['order'],'shipping_company');
	    if (!empty($shipping_company)){
	        $xmlRequest->{'delivery-spec'}->destination->addChild('company', $this->truncate($shipping_company, 44));
	    }
	    // if non 'CA':
	    if ($this->order_prop($order['order'],'shipping_country') != 'CA' && !empty($shipment['contact_phone'])){
	        // client-voice-number
	        $xmlRequest->{'delivery-spec'}->destination->addChild('client-voice-number', $shipment['contact_phone']);
	    }
    	   
	    //additional address info (above address line 1)
	    $xmlRequest->{'delivery-spec'}->destination->{'address-details'}->{'address-line-1'} = $this->order_prop($order['order'],'shipping_address_1');
	    $shipping_address_2 = $this->order_prop($order['order'],'shipping_address_2');
	    if (!empty($shipping_address_2)){
	        $xmlRequest->{'delivery-spec'}->destination->{'address-details'}->addChild('address-line-2', $shipping_address_2);
	    }
	    $xmlRequest->{'delivery-spec'}->destination->{'address-details'}->{'city'} =  $this->order_prop($order['order'],'shipping_city');
	    $xmlRequest->{'delivery-spec'}->destination->{'address-details'}->{'prov-state'} =  $this->order_prop($order['order'],'shipping_state');
	    $xmlRequest->{'delivery-spec'}->destination->{'address-details'}->{'country-code'} =  $this->order_prop($order['order'],'shipping_country');
	    $xmlRequest->{'delivery-spec'}->destination->{'address-details'}->{'postal-zip-code'} =  $shipping_postcode;
        if (empty($xmlRequest->{'delivery-spec'}->destination->{'address-details'}->{'prov-state'}) && $this->order_prop($order['order'],'shipping_country') != 'CA'){
            unset($xmlRequest->{'delivery-spec'}->destination->{'address-details'}->{'prov-state'});
        }
	    // Parcel
	    $xmlRequest->{'delivery-spec'}->{'parcel-characteristics'}->weight = number_format(floatval($shipment['package']['weight']),3,'.','');
	    $xmlRequest->{'delivery-spec'}->{'parcel-characteristics'}->dimensions->length = number_format(floatval($shipment['package']['length']),1,'.','');
	    $xmlRequest->{'delivery-spec'}->{'parcel-characteristics'}->dimensions->width = number_format(floatval($shipment['package']['width']),1,'.','');
	    $xmlRequest->{'delivery-spec'}->{'parcel-characteristics'}->dimensions->height = number_format(floatval($shipment['package']['height']),1,'.','');
	    if ($shipment['shipment_type'] == 'Document'){
	        $xmlRequest->{'delivery-spec'}->{'parcel-characteristics'}->document = 'true';
	    } 
	    if ($shipment['shipment_type'] == 'Mailing Tube') {
	        $xmlRequest->{'delivery-spec'}->{'parcel-characteristics'}->addChild('mailing-tube','true');
	    }
	    if ($shipment['shipment_type'] == 'Unpackaged') {
	        $xmlRequest->{'delivery-spec'}->{'parcel-characteristics'}->addChild('unpackaged','true');
	    }
	    // Email notification
	    if (!empty($shipment['destination_email'])){
	        $xmlRequest->{'delivery-spec'}->addChild('notification', '');
	        $xmlRequest->{'delivery-spec'}->notification->addChild('email', $shipment['destination_email']);
	        $xmlRequest->{'delivery-spec'}->notification->addChild('on-shipment', (!empty($shipment['email_on_shipment']) && $shipment['email_on_shipment']) ? 'true' : 'false');
	        $xmlRequest->{'delivery-spec'}->notification->addChild('on-exception', (!empty($shipment['email_on_exception']) && $shipment['email_on_exception']) ? 'true' : 'false');
	        $xmlRequest->{'delivery-spec'}->notification->addChild('on-delivery', (!empty($shipment['email_on_delivery']) && $shipment['email_on_delivery']) ? 'true' : 'false');
	    }
	    // Options
	    if (isset($shipment['opt_packinginstructions'])){
	       $xmlRequest->{'delivery-spec'}->preferences->{'show-packing-instructions'} = $shipment['opt_packinginstructions'] ? 'true' : 'false';
	    }
	    if (!empty($shipment['opt_postrate']) && $shipment['opt_postrate']){
	        $xmlRequest->{'delivery-spec'}->preferences->addChild('show-postage-rate', 'true');
	    }
	    if (!empty($shipment['opt_insuredvalue']) && $shipment['opt_insuredvalue']){
	        $xmlRequest->{'delivery-spec'}->preferences->addChild('show-insured-value', 'true');
	    }
	    $xmlRequest->{'delivery-spec'}->references->{'customer-ref-1'} = $shipment['reference'];
	    $xmlRequest->{'delivery-spec'}->references->{'customer-ref-2'} = $shipment['reference_additional'];
	    $xmlRequest->{'delivery-spec'}->references->{'cost-centre'} = $shipment['reference_cost'];
	    
	    if ($this->order_prop($order['order'],'shipping_country') != 'CA' && !empty($shipment['custom_products']) && is_array($shipment['custom_products'])){
	        // Add Customs.
	        $xmlCustoms = $xmlRequest->{'delivery-spec'}->addChild('customs', '');
	        $xmlCustoms->addChild('currency', $shipment['customs_currency']);
            if (!empty($shipment['customs_currency_conversion']) && $shipment['customs_currency'] != 'CAD'){
	            $xmlCustoms->addChild('conversion-from-cad', $shipment['customs_currency_conversion']);
	        }
	        $xmlCustoms->addChild('reason-for-export', $shipment['customs_export']);
	        if (!empty($shipment['customs_export_other'])){
	            $xmlCustoms->addChild('other-reason', $shipment['customs_export_other']);
	        }
	        $xmlCustoms->addChild('sku-list', '');
	        foreach ($shipment['custom_products'] as $item){ // max 500
	           $xmlItem = $xmlCustoms->{'sku-list'}->addChild('item','');
	           $xmlItem->addChild('customs-description', $item['description']);
	           $xmlItem->addChild('unit-weight', number_format(floatval($item['unitweight']),3,'.',''));
	           $xmlItem->addChild('customs-value-per-unit', $item['unitcost']);
	           $xmlItem->addChild('customs-number-of-units', $item['quantity']);
	           if(!empty($item['hs_code'])) { $xmlItem->addChild('hs-tariff-code', $item['hs_code']); }
	           if(!empty($item['sku'])) { $xmlItem->addChild('sku', $item['sku']); }
	           if(!empty($item['origin_prov'])) { $xmlItem->addChild('province-of-origin', $item['origin_prov']); }
	           if(!empty($item['origin_country'])) { $xmlItem->addChild('country-of-origin', $item['origin_country']); }
	        }
	        if (!empty($shipment['customs_invoice'])){
	            $xmlCustoms->addChild('invoice-number', $shipment['customs_invoice']);
	        }
	        if (!empty($shipment['customs_licenseid'])){
	            $xmlCustoms->addChild('licence-number', $shipment['customs_licenseid']);
	        }
	        if (!empty($shipment['customs_certificateid'])){
	            $xmlCustoms->addChild('certificate-number', $shipment['customs_certificateid']);
	        }
	        
	    }
	    if ($shipment['opt_signature'] || (!empty($shipment['insurance']) && $shipment['insurance'] > 0) || $this->order_prop($order['order'],'shipping_country') != 'CA'
	        || (!empty($shipment['opt_required']) && in_array($shipment['opt_required'], array('PA18', 'PA19')))
	        || (!empty($shipment['opt_delivery_door']) && in_array($shipment['opt_delivery_door'], array('HFP', 'DNS', 'LAD')))
	        ){
	        $xmlRequest->{'delivery-spec'}->addChild('options','');
	        // If Signature
	        if ($shipment['opt_signature']){
    	        $optSig = $xmlRequest->{'delivery-spec'}->options->addChild('option','');
    	        $optSig->addChild('option-code','SO'); // Signature
	        }
	        // If Insurance
	        if (!empty($shipment['insurance']) && $shipment['insurance'] > 0){
    	        $optIns = $xmlRequest->{'delivery-spec'}->options->addChild('option','');
    	        $optIns->addChild('option-code','COV');
    	        $optIns->addChild('option-amount', $shipment['insurance']); // $Insurance.
	        }
	        // If Required
	        if (!empty($shipment['opt_required']) && in_array($shipment['opt_required'], array('PA18', 'PA19'))){
	            $optReq = $xmlRequest->{'delivery-spec'}->options->addChild('option','');
	            $optReq->addChild('option-code',$shipment['opt_required']);
	        }
	        // If Delivery Option
	        if (!empty($shipment['opt_delivery_door']) && in_array($shipment['opt_delivery_door'], array('HFP', 'DNS', 'LAD'))){
	            $optDlv = $xmlRequest->{'delivery-spec'}->options->addChild('option','');
	            $optDlv->addChild('option-code',$shipment['opt_delivery_door']); 
	        }
	        // Deliver to Post Office.
	        //if (!empty($deliverToPostOffice)){
	        //    // (makes client-voice-number and name required under destination.)
	        //    $optDeliver2PO = $xmlCustoms->{'delivery-spec'}->options->addChild('option','');
	        //    $optDeliver2PO->addChild('option-code','D2PO');
	        //    $optDeliver2PO->addChild('option-qualifier-2',$deliverToPostOffice->office_id);
	        //}
	        //
	        if ($this->order_prop($order['order'],'shipping_country') != 'CA'){
    	        // Non-delivery for International.
    	        //RASE - Return at Sender’s Expense
    	        //RTS - Return to Sender
    	        //ABAN - Abandon
    	        $optNonDelivery = $xmlRequest->{'delivery-spec'}->options->addChild('option','');
    	        $optNonDelivery->addChild('option-code', !empty($shipment['customs_nondelivery']) ? $shipment['customs_nondelivery'] : 'RASE'); //RASE - Return at Sender’s Expense
	        }
	    }
	    // if promo-code.
	    if (!empty($shipment['opt_promocode'])){
	        $xmlRequest->{'delivery-spec'}->addChild('settlement-info','');
	        $xmlRequest->{'delivery-spec'}->{'settlement-info'}->addChild('promo-code',$shipment['opt_promocode']); // for development, use 'DEVPROTEST'
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
	    
	    $username = ($this->options->shipment_mode=='live') ? $this->options->api_user : $this->options->api_dev_user;
	    $password =  ($this->options->shipment_mode=='live') ? $this->options->api_key : $this->options->api_dev_key;
	    $account = $this->options->account;

	    // REST URL 
	    $service_url = ($this->options->shipment_mode=='live') ? 'https://soa-gw.canadapost.ca/rs/'.$account.'/ncshipment'  : 'https://ct.soa-gw.canadapost.ca/rs/'.$account.'/ncshipment'; // dev.  prod:
	    
	    try {
		// Create Shipment data
		$request_args = array(
			'method' => 'POST',
		    'httpversion' => apply_filters( 'http_request_version', '1.1' ),
		    'headers' => array( 'Accept' => 'application/vnd.cpc.ncshipment-v4+xml',
		                        'Content-Type' => 'application/vnd.cpc.ncshipment-v4+xml',
		                         'Accept-language' => $service_language,
			                     'Authorization' => 'Basic ' . base64_encode( $username . ':' . $password ) ),
		    'body' => $xmlRequest->asXML(),
		    'timeout' => 20,
		    'sslverify' => true,
		    'sslcertificates' => ABSPATH . WPINC . '/certificates/ca-bundle.crt'
		    );
		
	    $response = wp_remote_request($service_url, $request_args);
			    	
		if ( is_wp_error( $response ) ) {
		     $this->log->request['http'] = 'Failed. Error: ' . $response->get_error_code() . ": " . $response->get_error_message() . "\n";
		      //return;
		}
		
		// Retrieve http body
		$http_response = wp_remote_retrieve_body( $response );
		
		// Using SimpleXML to parse xml response
		libxml_use_internal_errors(true);
		$xml = simplexml_load_string('<root>' . preg_replace('/<\?xml.*\?>/','',$http_response) . '</root>');
		if (!$xml && ($this->options->shipment_log)) {
		    $errmsg = 'Failed loading XML' . "\n";
		    $errmsg .= $http_response . "\n";
		    foreach(libxml_get_errors() as $error) {
		        $errmsg.= "\t" . $error->message;
		    }
		    $this->log->request['errmsg'] = $errmsg;
		} else {
		    if ($xml->{'non-contract-shipment-info'} ) {
		        
		        $shipment_data = $xml->{'non-contract-shipment-info'}->children('http://www.canadapost.ca/ws/ncshipment-v4');
		        
		        $shipment['label']->id = (string)$shipment_data->{'shipment-id'};
		        $shipment['label']->pin = (string)$shipment_data->{'tracking-pin'};
		        
		        $shipment_links = $shipment_data->{'links'}->{'link'};
		        if ( $shipment_links ) {
		            foreach ( $shipment_links as $l ) {
		                $link_attr = $l->attributes();
		                $link = new stdClass();
		                $link->type = (string)$link_attr->{'rel'};
		                $link->href = (string) $link_attr->{'href'};
		                $link->media_type = (string)$link_attr->{'media-type'};
                        if (isset($link_attr->{'index'})){
                            $link->index = (string)$link_attr->{'index'};
                        }
		                // Add link
		                $shipment['label']->links[] = $link;
		            }
		        }
		        
		        // Save additional data
		        $shipment['label']->sender_postal =  $this->shipment_address[$index]['postalcode'];
		        $shipment['label']->sender_contact = $this->shipment_address[$index]['contact'];
		        $shipment['label']->destination_name = $this->order_prop($order['order'],'shipping_first_name') .' '. $this->order_prop($order['order'],'shipping_last_name');
		        $shipment['label']->destination_city = $this->order_prop($order['order'],'shipping_city');
		        $shipment['label']->destination_state = $this->order_prop($order['order'],'shipping_state');
		        $shipment['label']->destination_country = $this->order_prop($order['order'],'shipping_country');
		        $shipment['label']->destination_postal = $shipping_postcode;
		        // local wp date/time.
		        $shipment['label']->date_created = current_time( 'mysql' );
		    }

		    if ($xml->{'messages'} && ($this->options->shipment_log)) {
		        $apierror = '';
		        $messages = $xml->{'messages'}->children('http://www.canadapost.ca/ws/messages');
		        foreach ( $messages as $message ) {
		            $apierror .= 'Error Code: ' . $message->code . "\n";
		            $apierror .= 'Error Msg: ' . $message->description . "\n\n";
		        }
		        $this->log->request['apierror'] = $apierror;
		    }
		
		}
		} catch (Exception $ex) {
		    // Http request went wrong.
		    if ($this->options->shipment_log){
		        $this->log->request['error'] = 'Error: ' . $ex->getMessage();
		    }
		}
		if ( $this->options->shipment_log ){
		    $this->log->shipment = $shipment;
		    $this->log->datestamp = current_time('timestamp');
		    // Save to transient for 20 minutes.		    
		    $this->log->request_type = "Create Shipment";
		    $this->save_log($this->log);
		}
		
		return $shipment;
		
	}
	
	
	/*
	 * Api function to retrieve PDF shipping label.
	 */
	public function get_shipping_label($id, $label_url, $type = "pdf") {
	    
	    $result = '';
	    $message = '';
	    
	    // Check if file exists first.  Else Request from Api
	    $id = preg_replace('/[^a-z0-9\-]+/i', '', $id); // id can only be alphanumeric.
	    $label_filename = $id . ($type != "pdf" ? ".zpl" : ".pdf");
	    
	    if ($this->cachemode == 'filecache') {
    	    add_filter('upload_dir', array(&$this, 'custom_upload_subdir'));
    	    $upload_dir = wp_upload_dir();
    	    remove_filter('upload_dir', array(&$this, 'custom_upload_subdir'));
    	    
    	    if ( file_exists( $upload_dir['path'].'/'. $label_filename) && filesize( $upload_dir['path'].'/'. $label_filename ) > 0 ) {
    	        // Label has already been downloaded from Api.
    	        return $upload_dir['path'].'/'. $label_filename;
    	    }	    
	    } else if ($this->cachemode == 'dbcache'){
	        $dbdata = get_transient('cpwebservice_label_' . $id);
	        if (!empty($dbdata)){
	            $result = base64_decode($dbdata);
	            return $result;
	        }
	    }
	    
	    
	    $account = $this->options->account;
	    //$service_url =  (($this->options->shipment_mode=='live') ? 'https://soa-gw.canadapost.ca/'  : 'https://ct.soa-gw.canadapost.ca/' ) . $label_url;
	    if (strpos($label_url, 'https://soa-gw.canadapost.ca/')===0){
	        $username = $this->options->api_user;
	        $password = $this->options->api_key;
	        $service_url = $label_url;
	    }elseif (strpos($label_url, 'https://ct.soa-gw.canadapost.ca/')===0){
	        $username = $this->options->api_dev_user;
	        $password = $this->options->api_dev_key;
	        $service_url = $label_url;
	    } else {
	        // invalid url.
	        $message = 'Error: Invalid Url (Must use Canada Post Webservice Url.)' . "\n";
	        $this->log->request['service'] = $message;
	        return '';
	    }
	    
	    
	    $http_accept = $type != "pdf" ? "application/zpl" : "application/pdf";
	    
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
	    
	    try {
	        
    	    $request_args = array(
    	        'method' => 'GET',
    	        'httpversion' => apply_filters( 'http_request_version', '1.1' ),
    	        'headers' => array( 'Accept' => $http_accept,
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
			elseif(!empty($response['body']) && stristr($response['body'],"<messages xmlns=\"http://www.canadapost.ca/ws/messages\">")){
				// API Error.
				libxml_use_internal_errors(true);
				$xml = simplexml_load_string('<root>' . preg_replace('/<\?xml.*\?>/','',$response['body']) . '</root>');
				$api_error = ''; 
				if ($xml->{'messages'}) {  
					$messages = $xml->{'messages'}->children('http://www.canadapost.ca/ws/messages'); 
					foreach ( $messages as $msg ) {
						$api_error .= 'Error Code: ' . $msg->code . "\n";
						$api_error .= 'Error Msg: ' . $msg->description . "\n\n";
					}
				}
				
				$message .= 'Failed. Please try later. API Error: ' . $api_error . "\n";
			}
    	    elseif(!empty($response['body'])) {
    	     	
    	        // File Contents
    	        $label_file = $response['body'];
    	        
    	        if ($this->cachemode == 'filecache') {
        	        // Save File
        	        add_filter('upload_dir', array(&$this, 'custom_upload_subdir'));        
        	        $label = wp_upload_bits( $label_filename, null, $label_file );
        	        remove_filter('upload_dir', array(&$this, 'custom_upload_subdir')); 
        	        
        	        // ensure .htaccess is there.
        	        $this->ensure_subdir_security($upload_dir['path']);
        	        
        	        // Return Url of label
        	        $result = $label['file'];
    	        } 
    	        elseif($this->cachemode == 'dbcache') {
    	            //Save PDF to db
    	            $dbdata = base64_encode($label_file);
    	            set_transient('cpwebservice_label_' . $id, $dbdata,  1 * HOUR_IN_SECONDS);
    	            return $label_file;
    	        }
    	    }
	    
	    } catch (Exception $ex) {
	        // Http request went wrong.
	        $message = 'Error: ' . $ex->getMessage() . "\n";
	    }
	    $this->log->request['service'] = $message;
	    
	    return $result;
	}
	
	
	/*
	 * Api function for receipt details non-contract shipments
	 */
	public function nc_shipment_getdetails($shipment) {
	
        if (!empty($shipment) && isset($shipment['label']))
        {
            // Get $receipt_url
            $receipt_url = '';
            foreach($shipment['label']->links as $link){
                if ($link->type == 'receipt') {
                    $receipt_url = $link->href;
                }
            }
            if (!empty($receipt_url)) {
                
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
        	
        	    $username = ($this->options->shipment_mode=='live') ? $this->options->api_user : $this->options->api_dev_user;
        	    $password =  ($this->options->shipment_mode=='live') ? $this->options->api_key : $this->options->api_dev_key;
        	
        	    // REST URL
        	    if (strpos($receipt_url, 'https://soa-gw.canadapost.ca/')===0){
        	        $username = $this->options->api_user;
        	        $password = $this->options->api_key;
        	        $service_url = $receipt_url;
        	    }elseif (strpos($receipt_url, 'https://ct.soa-gw.canadapost.ca/')=== 0){
        	        $username = $this->options->api_dev_user;
        	        $password = $this->options->api_dev_key;
        	        $service_url = $receipt_url;
        	    } else {
        	        // invalid url.
        	        $message = 'Error: Invalid Url (Must use Canada Post Webservice Url.)' + "\n";
        	        $this->log->request['service'] = $message;
        	        return $shipment;
        	    }
        	
        	    try {
        	        // Request shipment receipt details via api.
        	        $request_args = array(
        	            'method' => 'GET',
        	            'httpversion' => apply_filters( 'http_request_version', '1.1' ),
        	            'headers' => array( 'Accept' => 'application/vnd.cpc.ncshipment-v4+xml',
        	                'Content-Type' => 'application/vnd.cpc.ncshipment-v4+xml',
        	                'Accept-language' => $service_language,
        	                'Authorization' => 'Basic ' . base64_encode( $username . ':' . $password ) ),
        	            'body' => null,
        	            'sslverify' => true,
        	            'sslcertificates' => ABSPATH . WPINC . '/certificates/ca-bundle.crt'
        	        );
        	
        	        $response = wp_remote_request($service_url, $request_args);
        	
        	        if ( is_wp_error( $response ) ) {
        	            $this->log->request['http'] = 'Failed. Error: ' . $response->get_error_code() . ": " . $response->get_error_message() . "\n";
        	            return $shipment;
        	        }
        	
        	        // Retrieve http body
        	        $http_response = wp_remote_retrieve_body( $response );
        	
        	        // Using SimpleXML to parse xml response
        	        libxml_use_internal_errors(true);
        	        $xml = simplexml_load_string('<root>' . preg_replace('/<\?xml.*\?>/','',$http_response) . '</root>');
        	        if (!$xml && ($this->options->shipment_log)) {
        	            $errmsg = 'Failed loading XML' . "\n";
        	            $errmsg .= $http_response . "\n";
        	            foreach(libxml_get_errors() as $error) {
        	                $errmsg.= "\t" . $error->message;
        	            }
        	            $this->log->request['errmsg'] = $errmsg;
        	        } else {
        	            if ($xml->{'non-contract-shipment-receipt'} ) {
        	
        	                $xml_data = $xml->{'non-contract-shipment-receipt'}->children();
        	
        	                // From 
        	                $shipment['label']->sender_postal = (string)$xml_data->{'final-shipping-point'};
        	                $shipment['label']->sender_name = (string)$xml_data->{'shipping-point-name'};
        	                $shipment['label']->sender_id = (string)$xml_data->{'shipping-point-id'};
        	                $shipment['label']->service_code = (string)$xml_data->{'service-code'};
        	                $shipment['label']->rated_weight = (string)$xml_data->{'rated-weight'};
        	                // Card  
        	                $shipment['label']->cost = (string)$xml_data->{'cc-receipt-details'}->{'charge-amount'};
        	                $shipment['label']->cost_currency = (string)$xml_data->{'cc-receipt-details'}->{'currency'};
        	                $shipment['label']->card_name = (string)$xml_data->{'cc-receipt-details'}->{'name-on-card'};
        	                $shipment['label']->card_type = (string)$xml_data->{'cc-receipt-details'}->{'card-type'}; //card-type [MC,VIS,AME]
        	            }
        	            if ($xml->{'messages'} && ($this->options->shipment_log)) {
        	                $apierror = '';
        	                $messages = $xml->{'messages'}->children('http://www.canadapost.ca/ws/messages');
        	                foreach ( $messages as $message ) {
        	                    $apierror .= 'Error Code: ' . $message->code . "\n";
        	                    $apierror .= 'Error Msg: ' . $message->description . "\n\n";
        	                }
        	                $this->log->request['apierror'] = $apierror;
        	            }
        	
        	        }
        	    } catch (Exception $ex) {
        	        // Http request went wrong.
        	        if ($this->options->shipment_log){
        	            $this->log->request['error'] = 'Error: ' . $ex->getMessage();
        	        }
        	    }
        	    
        	    if ( $this->options->shipment_log ){
        	        $this->log->shipment = $shipment;
        	        $this->log->datestamp = current_time('timestamp');
        	        // Save to transient for 20 minutes.
        	        $this->log->request_type = "Shipment Details";
        	        $this->save_log($this->log);
        	    }
            }
        } // endif
	   return $shipment['label'];
	
	}
	
	/*
	 * Api function for refunding non-contract shipments
	 */
	public function nc_shipment_refund($refund_url, $shipment_email) {
	     
	    $service_ticket = new stdClass();
	     
	    $xmlRequest = new SimpleXMLElement(<<<XML
<?xml version="1.0" encoding="UTF-8"?>
<non-contract-shipment-refund-request xmlns="http://www.canadapost.ca/ws/ncshipment-v4">
<email></email>
</non-contract-shipment-refund-request>
XML
	    );
	    
	    // Add admin email (required)
	    $xmlRequest->email = $shipment_email;
	    
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
	     
	   $username = ($this->options->shipment_mode=='live') ? $this->options->api_user : $this->options->api_dev_user;
	   $password =  ($this->options->shipment_mode=='live') ? $this->options->api_key : $this->options->api_dev_key;
	
	   // REST URL
	   if (strpos($refund_url, 'https://soa-gw.canadapost.ca/')===0){
	       $username = $this->options->api_user;
	       $password = $this->options->api_key;
	       $service_url = $refund_url;
	   }elseif (strpos($refund_url, 'https://ct.soa-gw.canadapost.ca/')===0){
	       $username = $this->options->api_dev_user;
	       $password = $this->options->api_dev_key;
	       $service_url = $refund_url;
	   } else {
	       // invalid url.
	       $message = 'Error: Invalid Url (Must use Canada Post Webservice Url.)' + "\n";
	       $this->log->request['service'] = $message;
	       return '';
	   }
	     
	    try {
	        // Request shipment refund via api.
	        $request_args = array(
	            'method' => 'POST',
	            'httpversion' => apply_filters( 'http_request_version', '1.1' ),
	            'headers' => array( 'Accept' => 'application/vnd.cpc.ncshipment-v4+xml',
	                'Content-Type' => 'application/vnd.cpc.ncshipment-v4+xml',
	                'Accept-language' => $service_language,
	                'Authorization' => 'Basic ' . base64_encode( $username . ':' . $password ) ),
	            'body' => $xmlRequest->asXML(),
	            'sslverify' => true,
	            'sslcertificates' => ABSPATH . WPINC . '/certificates/ca-bundle.crt' // More up-to-date than the ones I'm bundling.
	        );
	
	        $response = wp_remote_request($service_url, $request_args);
	
	        if ( is_wp_error( $response ) ) {
	            $this->log->request['http'] = 'Failed. Error: ' . $response->get_error_code() . ": " . $response->get_error_message() . "\n";
	            return;
	        }
	
	        // Retrieve http body
	        $http_response = wp_remote_retrieve_body( $response );
	
	        // Using SimpleXML to parse xml response
	        libxml_use_internal_errors(true);
	        $xml = simplexml_load_string('<root>' . preg_replace('/<\?xml.*\?>/','',$http_response) . '</root>');
	        if (!$xml && ($this->options->shipment_log)) {
	            $errmsg = 'Failed loading XML' . "\n";
	            $errmsg .= $http_response . "\n";
	            foreach(libxml_get_errors() as $error) {
	                $errmsg.= "\t" . $error->message;
	            }
	            $this->log->request['errmsg'] = $errmsg;
	        } else {
	            if ($xml->{'non-contract-shipment-refund-request-info'} ) {
	
	                $xml_data = $xml->{'non-contract-shipment-refund-request-info'}->children();
	
	                $service_ticket->date = (string)$xml_data->{'service-ticket-date'};
	                $service_ticket->ticket_id = (string)$xml_data->{'service-ticket-id'};
	                $service_ticket->email = $shipment_email;
	                
	            }
	            if ($xml->{'messages'} && ($this->options->shipment_log)) {
	                $apierror = '';
	                $messages = $xml->{'messages'}->children('http://www.canadapost.ca/ws/messages');
	                foreach ( $messages as $message ) {
	                    $apierror .= 'Error Code: ' . $message->code . "\n";
	                    $apierror .= 'Error Msg: ' . $message->description . "\n\n";
	                }
	                $this->log->request['apierror'] = $apierror;
	            }
	
	        }
	    } catch (Exception $ex) {
	        // Http request went wrong.
	        if ($this->options->shipment_log){
	            $this->log->request['error'] = 'Error: ' . $ex->getMessage();
	        }
	    }
	    
	    if ( $this->options->shipment_log ){
	        $this->log->datestamp = current_time('timestamp');
	        // Save to transient for 20 minutes.
	        $this->log->request_type = "Shipment Refund";
	        $this->save_log($this->log);
	    }
	
	    return $service_ticket;

	}
	
	
	/*
	 * Contact Shipments!
	 */
	/*
	 * API function for Creating Contract shipments
	 */
	public function create_ct_shipment($shipment, $order, $package_index, $sender, $mode) {
	     
	    // Currently only $mode=='transmit' is valid.
	    if ($mode == 'manifest'){
	        // Error!
	        $this->log->request['apierror'] = 'Error: Manifest Transmit functionality is not yet implemented in this version of the plugin.';
	        return null;
	    }
	    // Resulting label will be stored in shipment object.
	    $shipment['label'] = new stdClass();
	    $shipment['label']->links = array();
	     
	    $xmlRequest = new SimpleXMLElement(<<<XML
<?xml version="1.0" encoding="UTF-8"?>
<shipment xmlns="http://www.canadapost.ca/ws/shipment-v8">
	<requested-shipping-point></requested-shipping-point>
	<delivery-spec>
		<service-code></service-code>
		<sender>
	        <name></name>
	        <company></company>
	        <contact-phone></contact-phone>
			<address-details>
				<address-line-1></address-line-1>
				<city></city>
				<prov-state></prov-state>
				<postal-zip-code></postal-zip-code>
			</address-details>
		</sender>
		<destination>
			<name></name>
			<address-details>
				<address-line-1></address-line-1>
				<city></city>
				<prov-state></prov-state>
				<country-code></country-code>
				<postal-zip-code></postal-zip-code>
			</address-details>
		</destination>
		<parcel-characteristics>
			<weight></weight>
			<dimensions>
				<length></length>
				<width></width>
				<height></height>
			</dimensions>
		</parcel-characteristics>
	    <print-preferences>
            <output-format>8.5x11</output-format>
        </print-preferences>
		<preferences>
			<show-packing-instructions>true</show-packing-instructions>
		</preferences>
		<references>
			<customer-ref-1></customer-ref-1>
			<customer-ref-2></customer-ref-2>
	    </references>
	</delivery-spec>
</shipment>
XML
	   );
	    
	    $xmlRequest->addChild('v8:transmit-shipment','true');
	    // Provide data to create shipment request xml
	    $index = intval($shipment['sender_address_index']);
	    $shipping_postcode = str_replace(' ','',strtoupper($this->order_prop($order['order'], 'shipping_postcode'))); //N0N0N0 (no spaces, uppercase)
	    $this->shipment_address[$index]['postalcode'] = str_replace(' ','',strtoupper($this->shipment_address[$index]['postalcode'])); //N0N0N0 (no spaces, uppercase)
	    //$xmlRequest->addChild('customer-request-id', $shipment_id = '_'.uniqid()); // if request-id is needed.
	    //if ($mode=='manifest'){ $groupid }
	    if (!empty($shipment['shipping_point_id']) && !empty($shipment['pickup_indicator']) && $shipment['pickup_indicator']=='dropoff'){
	        // Deposit location
	        $xmlRequest->addChild('shipping-point-id', $shipment['shipping_point_id']); // 4-character alphanumeric string
	        unset($xmlRequest->{'requested-shipping-point'}); // mutually exclusive
	    } else {
	        $xmlRequest->{'requested-shipping-point'} = $this->shipment_address[$index]['postalcode'];
	       if (!empty($shipment['pickup_indicator']) && $shipment['pickup_indicator']=='pickup'){
	        // Pickup from requested-shipping-point.
	        $xmlRequest->addChild('cpc-pickup-indicator', 'true');
	       }
	    }
	    // Set Service code.
	    $xmlRequest->{'delivery-spec'}->{'service-code'} = $shipment['method_id'];
	    // Sender (Canadian address)
	    $xmlRequest->{'delivery-spec'}->sender->name = $this->shipment_address[$index]['contact'];
        if (!empty($this->shipment_address[$index]['contact'])){
          $xmlRequest->{'delivery-spec'}->sender->company = $this->shipment_address[$index]['contact'];
        }
	    $xmlRequest->{'delivery-spec'}->sender->{'contact-phone'} = $this->shipment_address[$index]['phone'];
	    $xmlRequest->{'delivery-spec'}->sender->{'address-details'}->{'address-line-1'} = $this->truncate($this->shipment_address[$index]['address'], 44);
	    if (!empty($this->shipment_address[$index]['address2'])){
	        $xmlRequest->{'delivery-spec'}->sender->{'address-details'}->addChild('address-line-2', $this->truncate($this->shipment_address[$index]['address2'], 44));
	    }
	    $xmlRequest->{'delivery-spec'}->sender->{'address-details'}->{'city'} = $this->truncate($this->shipment_address[$index]['city'], 40);
	    $xmlRequest->{'delivery-spec'}->sender->{'address-details'}->{'prov-state'} = $this->shipment_address[$index]['prov'];
	    $xmlRequest->{'delivery-spec'}->sender->{'address-details'}->{'country-code'} = 'CA'; //$this->shipment_address[$index]['country'];
	    $xmlRequest->{'delivery-spec'}->sender->{'address-details'}->{'postal-zip-code'} = $this->shipment_address[$index]['postalcode'];
	    // Destination
	    $xmlRequest->{'delivery-spec'}->destination->name = $this->order_prop($order['order'],'shipping_first_name') .' '. $this->order_prop($order['order'],'shipping_last_name');
	    $shipping_company = $this->order_prop($order['order'],'shipping_company');
	    if (!empty($shipping_company)){
	        $xmlRequest->{'delivery-spec'}->destination->addChild('company', $this->truncate($shipping_company, 44));
	    }
	    // if non 'CA':
	    if ($this->order_prop($order['order'],'shipping_country') != 'CA' && !empty($shipment['contact_phone'])){
	        // client-voice-number
	        $xmlRequest->{'delivery-spec'}->destination->addChild('client-voice-number', $shipment['contact_phone']);
	    }
	
	    //additional address info (above address line 1)
	    $xmlRequest->{'delivery-spec'}->destination->{'address-details'}->{'address-line-1'} = $this->order_prop($order['order'],'shipping_address_1');
	    $shipping_address_2 = $this->order_prop($order['order'],'shipping_address_2');
	    if (!empty($shipping_address_2)){
	        $xmlRequest->{'delivery-spec'}->destination->{'address-details'}->addChild('address-line-2', $shipping_address_2);
	    }
	    $xmlRequest->{'delivery-spec'}->destination->{'address-details'}->{'city'} =  $this->order_prop($order['order'],'shipping_city');
	    $xmlRequest->{'delivery-spec'}->destination->{'address-details'}->{'prov-state'} =  $this->order_prop($order['order'],'shipping_state');
	    $xmlRequest->{'delivery-spec'}->destination->{'address-details'}->{'country-code'} =  $this->order_prop($order['order'],'shipping_country');
	    $xmlRequest->{'delivery-spec'}->destination->{'address-details'}->{'postal-zip-code'} =  $shipping_postcode;
	    // Parcel
	    $xmlRequest->{'delivery-spec'}->{'parcel-characteristics'}->weight = number_format(floatval($shipment['package']['weight']),3,'.','');
	    $xmlRequest->{'delivery-spec'}->{'parcel-characteristics'}->dimensions->length = number_format(floatval($shipment['package']['length']),1,'.','');
	    $xmlRequest->{'delivery-spec'}->{'parcel-characteristics'}->dimensions->width = number_format(floatval($shipment['package']['width']),1,'.','');
	    $xmlRequest->{'delivery-spec'}->{'parcel-characteristics'}->dimensions->height = number_format(floatval($shipment['package']['height']),1,'.','');
	    
	    if ($shipment['shipment_type'] == 'Document'){
	        $xmlRequest->{'delivery-spec'}->{'parcel-characteristics'}->document = 'true';
	    }
	    if ($shipment['shipment_type'] == 'Mailing Tube') {
	        $xmlRequest->{'delivery-spec'}->{'parcel-characteristics'}->addChild('mailing-tube','true');
	    }
	    if ($shipment['shipment_type'] == 'Unpackaged') {
	        $xmlRequest->{'delivery-spec'}->{'parcel-characteristics'}->addChild('unpackaged','true');
	    }
	    // Email notification
	    if (!empty($shipment['destination_email'])){
	        $xmlRequest->{'delivery-spec'}->addChild('notification', '');
	        $xmlRequest->{'delivery-spec'}->notification->addChild('email', $shipment['destination_email']);
	        $xmlRequest->{'delivery-spec'}->notification->addChild('on-shipment', (!empty($shipment['email_on_shipment']) && $shipment['email_on_shipment']) ? 'true' : 'false');
	        $xmlRequest->{'delivery-spec'}->notification->addChild('on-exception', (!empty($shipment['email_on_exception']) && $shipment['email_on_exception']) ? 'true' : 'false');
	        $xmlRequest->{'delivery-spec'}->notification->addChild('on-delivery', (!empty($shipment['email_on_delivery']) && $shipment['email_on_delivery']) ? 'true' : 'false');
	    }
	    // Options
	    if (!empty($shipment['opt_packinginstructions']) && $shipment['opt_packinginstructions']){
	        $xmlRequest->{'delivery-spec'}->preferences->{'show-packing-instructions'} = 'true';
	    }
	    if (!empty($shipment['opt_postrate']) && $shipment['opt_postrate']){
	        $xmlRequest->{'delivery-spec'}->preferences->addChild('show-postage-rate', 'true');
	    }
	    if (!empty($shipment['opt_insuredvalue']) && $shipment['opt_insuredvalue']){
	        $xmlRequest->{'delivery-spec'}->preferences->addChild('show-insured-value', 'true');
	    }
	    if (!empty($shipment['opt_outputformat']) && $shipment['opt_outputformat'] == '4x6'){
	        $xmlRequest->{'delivery-spec'}->{'print-preferences'}->{'output-format'} = '4x6';
	    }
	    $xmlRequest->{'delivery-spec'}->references->{'customer-ref-1'} = $shipment['reference'];
	    $xmlRequest->{'delivery-spec'}->references->{'customer-ref-2'} = $shipment['reference_additional'];
	    $xmlRequest->{'delivery-spec'}->references->{'cost-centre'} = $shipment['reference_cost'];
	     
	    if ($this->order_prop($order['order'],'shipping_country') != 'CA' && !empty($shipment['custom_products']) && is_array($shipment['custom_products'])){
	        // Add Customs.
	        $xmlCustoms = $xmlRequest->{'delivery-spec'}->addChild('customs', '');
	        $xmlCustoms->addChild('currency', $shipment['customs_currency']);
            if (!empty($shipment['customs_currency_conversion']) && $shipment['customs_currency'] != 'CAD'){
	            $xmlCustoms->addChild('conversion-from-cad', $shipment['customs_currency_conversion']);
	        }
	        $xmlCustoms->addChild('reason-for-export', $shipment['customs_export']);
	        if (!empty($shipment['customs_export_other'])){
	            $xmlCustoms->addChild('other-reason', $shipment['customs_export_other']);
	        }
	        $xmlCustoms->addChild('sku-list', '');
	        foreach ($shipment['custom_products'] as $item){ // max 500
	            $xmlItem = $xmlCustoms->{'sku-list'}->addChild('item','');
	            $xmlItem->addChild('customs-description', $item['description']);
	            $xmlItem->addChild('unit-weight', number_format(floatval($item['unitweight']),3,'.',''));
	            $xmlItem->addChild('customs-value-per-unit', $item['unitcost']);
	            $xmlItem->addChild('customs-number-of-units', $item['quantity']);
	            if(!empty($item['hs_code'])) { $xmlItem->addChild('hs-tariff-code', $item['hs_code']); }
	            if(!empty($item['sku'])) { $xmlItem->addChild('sku', $item['sku']); }
	            if(!empty($item['origin_prov'])) { $xmlItem->addChild('province-of-origin', $item['origin_prov']); }
	            if(!empty($item['origin_country'])) { $xmlItem->addChild('country-of-origin', $item['origin_country']); }
	        }
	        if (!empty($shipment['customs_invoice'])){
	            $xmlCustoms->addChild('invoice-number', $shipment['customs_invoice']);
	        }
	        if (!empty($shipment['customs_licenseid'])){
	            $xmlCustoms->addChild('licence-number', $shipment['customs_licenseid']);
	        }
	        if (!empty($shipment['customs_certificateid'])){
	            $xmlCustoms->addChild('certificate-number', $shipment['customs_certificateid']);
	        }
	         
	    }
	    if ($shipment['opt_signature'] || (!empty($shipment['insurance']) && $shipment['insurance'] > 0) || $this->order_prop($order['order'],'shipping_country') != 'CA'
	        || (!empty($shipment['opt_required']) && in_array($shipment['opt_required'], array('PA18', 'PA19')))
	        || (!empty($shipment['opt_delivery_door']) && in_array($shipment['opt_delivery_door'], array('HFP', 'DNS', 'LAD')))
	        ){
	        $xmlRequest->{'delivery-spec'}->addChild('options','');
	        // If Signature
	        if ($shipment['opt_signature']){
	            $optSig = $xmlRequest->{'delivery-spec'}->options->addChild('option','');
	            $optSig->addChild('option-code','SO'); // Signature
	        }
	        // If Insurance
	        if (!empty($shipment['insurance']) && $shipment['insurance'] > 0){
	            $optIns = $xmlRequest->{'delivery-spec'}->options->addChild('option','');
	            $optIns->addChild('option-code','COV');
	            $optIns->addChild('option-amount', $shipment['insurance']); // $Insurance.
	        }
	        // If Required
	        if (!empty($shipment['opt_required']) && in_array($shipment['opt_required'], array('PA18', 'PA19'))){
	            $optReq = $xmlRequest->{'delivery-spec'}->options->addChild('option','');
	            $optReq->addChild('option-code',$shipment['opt_required']);
	        }
	        // If Delivery Option
	        if (!empty($shipment['opt_delivery_door']) && in_array($shipment['opt_delivery_door'], array('HFP', 'DNS', 'LAD'))){
	            $optDlv = $xmlRequest->{'delivery-spec'}->options->addChild('option','');
	            $optDlv->addChild('option-code',$shipment['opt_delivery_door']);
	        }
	        // Deliver to Post Office.
	        //if (!empty($deliverToPostOffice)){
	        //    // (makes client-voice-number and name required under destination.)
	        //    $optDeliver2PO = $xmlCustoms->{'delivery-spec'}->options->addChild('option','');
	        //    $optDeliver2PO->addChild('option-code','D2PO');
	        //    $optDeliver2PO->addChild('option-qualifier-2',$deliverToPostOffice->office_id);
	        //}
	        //
	        if ($this->order_prop($order['order'],'shipping_country') != 'CA'){
	            // Non-delivery for International.
	            //RASE - Return at Sender’s Expense
	            //RTS - Return to Sender
	            //ABAN - Abandon
	            $optNonDelivery = $xmlRequest->{'delivery-spec'}->options->addChild('option','');
	            $optNonDelivery->addChild('option-code', !empty($shipment['customs_nondelivery']) ? $shipment['customs_nondelivery'] : 'RASE'); //RASE - Return at Sender’s Expense
	        }
	    }
	    // if return-spec (Return labels can be specified to be created)
	    
	    // Settlement info
	    $xmlRequest->{'delivery-spec'}->addChild('settlement-info',''); 
    	$xmlRequest->{'delivery-spec'}->{'settlement-info'}->addChild('paid-by-customer', $this->options->account);
	    $xmlRequest->{'delivery-spec'}->{'settlement-info'}->addChild('contract-id', $this->options->contractid);
	    $xmlRequest->{'delivery-spec'}->{'settlement-info'}->addChild('intended-method-of-payment', ($shipment['payment_method'] == 'Account') ? 'Account' : 'CreditCard' ); //Values: CreditCard, Account, SupplierAccount
	    
	    // if promo-code.
	    if (!empty($shipment['opt_promocode'])){
	        $xmlRequest->{'delivery-spec'}->{'settlement-info'}->addChild('promo-code',$shipment['opt_promocode']); // for development, use 'DEVPROTEST'
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
	     
	    $username = ($this->options->shipment_mode=='live') ? $this->options->api_user : $this->options->api_dev_user;
	    $password =  ($this->options->shipment_mode=='live') ? $this->options->api_key : $this->options->api_dev_key;
	    $account = $this->options->account;
	    $mobo = $this->options->account;
	
	    // REST URL
	    $service_url = ($this->options->shipment_mode=='live') ? 'https://soa-gw.canadapost.ca/rs/'.$account.'/'.$mobo.'/shipment'  : 'https://ct.soa-gw.canadapost.ca/rs/'.$account.'/'.$mobo.'/shipment'; // dev.  prod:
	     
	    try {
	        // Create Shipment data
	        $request_args = array(
	            'method' => 'POST',
	            'httpversion' => apply_filters( 'http_request_version', '1.1' ),
	            'headers' => array( 'Accept' => 'application/vnd.cpc.shipment-v8+xml',
	                'Content-Type' => 'application/vnd.cpc.shipment-v8+xml',
	                'Accept-language' => $service_language,
	                'Authorization' => 'Basic ' . base64_encode( $username . ':' . $password ) ),
	            'body' => $xmlRequest->asXML(),
	            'timeout' => 15,
	            'sslverify' => true,
	            'sslcertificates' => ABSPATH . WPINC . '/certificates/ca-bundle.crt' // More up-to-date than the ones I'm bundling.
	        );
	
	        $response = wp_remote_request($service_url, $request_args);
	
	        if ( is_wp_error( $response ) ) {
	            $this->log->request['http'] = 'Failed. Error: ' . $response->get_error_code() . ": " . $response->get_error_message() . "\n";
	            //return;
	        }
	
	        // Retrieve http body
	        $http_response = wp_remote_retrieve_body( $response );
	
	        // Using SimpleXML to parse xml response
	        libxml_use_internal_errors(true);
	        $xml = simplexml_load_string('<root>' . preg_replace('/<\?xml.*\?>/','',$http_response) . '</root>');
	        if (!$xml && ($this->options->shipment_log)) {
	            $errmsg = 'Failed loading XML' . "\n";
	            $errmsg .= $http_response . "\n";
	            foreach(libxml_get_errors() as $error) {
	                $errmsg.= "\t" . $error->message;
	            }
	            $this->log->request['errmsg'] = $errmsg;
	        } else {
	            if ($xml->{'shipment-info'} ) {
	
	                $shipment_data = $xml->{'shipment-info'}->children();
	
	                $shipment['label']->id = (string)$shipment_data->{'shipment-id'};
	                $shipment['label']->pin = (string)$shipment_data->{'tracking-pin'};
	                $shipment['label']->status = (string)$shipment_data->{'status'};
	
	                $shipment_links = $shipment_data->{'links'}->{'link'};
	                if ( $shipment_links ) {
	                    foreach ( $shipment_links as $l ) {
	                        $link_attr = $l->attributes();
	                        $link = new stdClass();
	                        $link->type = (string)$link_attr->{'rel'};
	                        $link->href = (string) $link_attr->{'href'};
	                        $link->media_type = (string)$link_attr->{'media-type'};
                            if (isset($link_attr->{'index'})){
                                $link->index = (string)$link_attr->{'index'};
                            }
	                        // Add link
	                        $shipment['label']->links[] = $link;
	                    }
	                }
	
	                // Save additional data
	                $shipment['label']->sender_postal =  $this->shipment_address[$index]['postalcode'];
	                $shipment['label']->sender_contact = $this->shipment_address[$index]['contact'];
	                $shipment['label']->destination_name = $this->order_prop($order['order'],'shipping_first_name') .' '. $this->order_prop($order['order'],'shipping_last_name');
	                $shipment['label']->destination_city = $this->order_prop($order['order'],'shipping_city');
	                $shipment['label']->destination_state = $this->order_prop($order['order'],'shipping_state');
	                $shipment['label']->destination_country = $this->order_prop($order['order'],'shipping_country');
	                $shipment['label']->destination_postal = $shipping_postcode;
	                // local wp date/time.
	                $shipment['label']->date_created = current_time( 'mysql' );
	
	            }
	            if ($xml->{'messages'} && ($this->options->shipment_log)) {
	                $apierror = '';
	                $messages = $xml->{'messages'}->children('http://www.canadapost.ca/ws/messages');
	                foreach ( $messages as $message ) {
	                    $apierror .= 'Error Code: ' . $message->code . "\n";
	                    $apierror .= 'Error Msg: ' . $message->description . "\n\n";
	                }
	                $this->log->request['apierror'] = $apierror;
	            }
	
	        }
	    } catch (Exception $ex) {
	        // Http request went wrong.
	        if ($this->options->shipment_log){
	            $this->log->request['error'] = 'Error: ' . $ex->getMessage();
	        }
	    }
	    if ( $this->options->shipment_log ){
	        $this->log->shipment = $shipment;
	        $this->log->datestamp = current_time('timestamp');
	        // Save to transient for 20 minutes.
	        $this->log->request_type = "Create Contract Shipment";
	        $this->save_log($this->log);
	    }
	
	    return $shipment;
	
	}
	
	/*
	 * Function to get price details from contract shipments
	 */
	public function ct_shipment_getdetails($shipment){
	    if (!empty($shipment) && isset($shipment['label']))
	    {
	        // Get $price_url
	        $price_url = '';
	        foreach($shipment['label']->links as $link){
	            if ($link->type == 'price') {
	                $price_url = $link->href;
	            }
	        }
	        if (!empty($price_url)) {
	    
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
	             
	            $username = ($this->options->shipment_mode=='live') ? $this->options->api_user : $this->options->api_dev_user;
	            $password =  ($this->options->shipment_mode=='live') ? $this->options->api_key : $this->options->api_dev_key;
	             
	            // REST URL
	            if (strpos($price_url, 'https://soa-gw.canadapost.ca/')===0){
	                $username = $this->options->api_user;
	                $password = $this->options->api_key;
	                $service_url = $price_url;
	            }elseif (strpos($price_url, 'https://ct.soa-gw.canadapost.ca/')=== 0){
	                $username = $this->options->api_dev_user;
	                $password = $this->options->api_dev_key;
	                $service_url = $price_url;
	            } else {
	                // invalid url.
	                $message = 'Error: Invalid Url (Must use Canada Post Webservice Url.)' + "\n";
	                $this->log->request['service'] = $message;
	                return $shipment;
	            }
	             
	            try {
	                // Request shipment receipt details via api.
	                $request_args = array(
	                    'method' => 'GET',
	                    'httpversion' => apply_filters( 'http_request_version', '1.1' ),
	                    'headers' => array( 'Accept' => 'application/vnd.cpc.shipment-v8+xml',
	                        'Content-Type' => 'application/vnd.cpc.shipment-v8+xml',
	                        'Accept-language' => $service_language,
	                        'Authorization' => 'Basic ' . base64_encode( $username . ':' . $password ) ),
	                    'body' => null,
	                    'sslverify' => true,
	                    'sslcertificates' => ABSPATH . WPINC . '/certificates/ca-bundle.crt' // More up-to-date than the ones I'm bundling.
	                );
	                 
	                $response = wp_remote_request($service_url, $request_args);
	                 
	                if ( is_wp_error( $response ) ) {
	                    $this->log->request['http'] = 'Failed. Error: ' . $response->get_error_code() . ": " . $response->get_error_message() . "\n";
	                    return $shipment;
	                }
	                 
	                // Retrieve http body
	                $http_response = wp_remote_retrieve_body( $response );
	                 
	                // Using SimpleXML to parse xml response
	                libxml_use_internal_errors(true);
	                $xml = simplexml_load_string('<root>' . preg_replace('/<\?xml.*\?>/','',$http_response) . '</root>');
	                if (!$xml && ($this->options->shipment_log)) {
	                    $errmsg = 'Failed loading XML' . "\n";
	                    $errmsg .= $http_response . "\n";
	                    foreach(libxml_get_errors() as $error) {
	                        $errmsg.= "\t" . $error->message;
	                    }
	                    $this->log->request['errmsg'] = $errmsg;
	                } else {
	                    if ($xml->{'shipment-price'} ) {
	                         
	                        $xml_data = $xml->{'shipment-price'}->children();
	                         
	                        // From
// 	                        $shipment['label']->sender_postal = (string)$xml_data->{'final-shipping-point'};
// 	                        $shipment['label']->sender_name = (string)$xml_data->{'shipping-point-name'};
// 	                        $shipment['label']->sender_id = (string)$xml_data->{'shipping-point-id'};
	                        $shipment['label']->service_code = (string)$xml_data->{'service-code'};
	                        $shipment['label']->rated_weight = (string)$xml_data->{'rated-weight'};
	                        // Card
	                        $shipment['label']->cost = (string)$xml_data->{'due-amount'};
	                    }
	                    if ($xml->{'messages'} && ($this->options->shipment_log)) {
	                        $apierror = '';
	                        $messages = $xml->{'messages'}->children('http://www.canadapost.ca/ws/messages');
	                        foreach ( $messages as $message ) {
	                            $apierror .= 'Error Code: ' . $message->code . "\n";
	                            $apierror .= 'Error Msg: ' . $message->description . "\n\n";
	                        }
	                        $this->log->request['apierror'] = $apierror;
	                    }
	                     
	                }
	            } catch (Exception $ex) {
	                // Http request went wrong.
	                if ($this->options->shipment_log){
	                    $this->log->request['error'] = 'Error: ' . $ex->getMessage();
	                }
	            }
	             
	            if ( $this->options->shipment_log ){
	                $this->log->shipment = $shipment;
	                $this->log->datestamp = current_time('timestamp');
	                // Save to transient for 20 minutes.
	                $this->log->request_type = "Contract Shipment Details";
	                $this->save_log($this->log);
	            }
	        }
	    } // endif
	    return $shipment['label'];
	}

    /*
	 * Function to get receipt details from contract shipments
	 */
	public function ct_shipment_getreceipt($shipment){
	    if (!empty($shipment) && isset($shipment['label']))
	    {
	        // Get $receipt_url
	        $receipt_url = '';
	        foreach($shipment['label']->links as $link){
	            if ($link->type == 'receipt') {
	                $receipt_url = $link->href;
	            }
	        }
	        if (!empty($receipt_url)) {
	    
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
	             
	            $username = ($this->options->shipment_mode=='live') ? $this->options->api_user : $this->options->api_dev_user;
	            $password =  ($this->options->shipment_mode=='live') ? $this->options->api_key : $this->options->api_dev_key;
	             
	            // REST URL
	            if (strpos($receipt_url, 'https://soa-gw.canadapost.ca/')===0){
	                $username = $this->options->api_user;
	                $password = $this->options->api_key;
	                $service_url = $receipt_url;
	            }elseif (strpos($receipt_url, 'https://ct.soa-gw.canadapost.ca/')=== 0){
	                $username = $this->options->api_dev_user;
	                $password = $this->options->api_dev_key;
	                $service_url = $receipt_url;
	            } else {
	                // invalid url.
	                $message = 'Error: Invalid Url (Must use Canada Post Webservice Url.)' + "\n";
	                $this->log->request['service'] = $message;
	                return $shipment;
	            }
	             
	            try {
	                // Request shipment receipt details via api.
	                $request_args = array(
	                    'method' => 'GET',
	                    'httpversion' => apply_filters( 'http_request_version', '1.1' ),
	                    'headers' => array( 'Accept' => 'application/vnd.cpc.shipment-v8+xml',
	                        'Content-Type' => 'application/vnd.cpc.shipment-v8+xml',
	                        'Accept-language' => $service_language,
	                        'Authorization' => 'Basic ' . base64_encode( $username . ':' . $password ) ),
	                    'body' => null,
	                    'sslverify' => true,
	                    'sslcertificates' => ABSPATH . WPINC . '/certificates/ca-bundle.crt' // More up-to-date than the ones I'm bundling.
	                );
	                 
	                $response = wp_remote_request($service_url, $request_args);
	                 
	                if ( is_wp_error( $response ) ) {
	                    $this->log->request['http'] = 'Failed. Error: ' . $response->get_error_code() . ": " . $response->get_error_message() . "\n";
	                    return $shipment;
	                }
	                 
	                // Retrieve http body
	                $http_response = wp_remote_retrieve_body( $response );
	                 
	                // Using SimpleXML to parse xml response
	                libxml_use_internal_errors(true);
	                $xml = simplexml_load_string('<root>' . preg_replace('/<\?xml.*\?>/','',$http_response) . '</root>');
	                if (!$xml && ($this->options->shipment_log)) {
	                    $errmsg = 'Failed loading XML' . "\n";
	                    $errmsg .= $http_response . "\n";
	                    foreach(libxml_get_errors() as $error) {
	                        $errmsg.= "\t" . $error->message;
	                    }
	                    $this->log->request['errmsg'] = $errmsg;
	                } else {

                        if ($xml->{'shipment-cc-receipt'} ) {
                            $receipt = $xml->{'shipment-receipt'}->children('http://www.canadapost.ca/ws/shipment-v8');
                            $shipment['label']->receipt = new stdClass();
                            if ( $receipt->{'cc-receipt-details'} ) {
                                $receipt_cc = $receipt->{'cc-receipt-details'};
                                $shipment['label']->receipt->type = 'Credit Card';
                                $shipment['label']->receipt->merchant_name = $receipt_cc->{'merchant-name'};
                                $shipment['label']->receipt->merchant_url  = $receipt_cc->{'merchant-url'};
                                $shipment['label']->receipt->name_on_card  = $receipt_cc->{'name-on-card'};
                                $shipment['label']->receipt->auth_code     = $receipt_cc->{'auth-code'};
                                $shipment['label']->receipt->auth_timestamp= $receipt_cc->{'auth-timestamp'};
                                $shipment['label']->receipt->card_type     = $receipt_cc->{'card-type'};
                                $shipment['label']->receipt->charge_amount = $receipt_cc->{'charge-amount'};
                                $shipment['label']->receipt->currency      = $receipt_cc->{'currency'};
                            } else if ($receipt->{'supplier-account-receipt-details'}){
                                $receipt_account = $receipt->{'supplier-account-receipt-details'};
                                $shipment['label']->receipt->type = 'Account';
                                $shipment['label']->receipt->merchant_name = $receipt_account->{'merchant-name'};
                                $shipment['label']->receipt->merchant_url  = $receipt_account->{'merchant-url'};
                                $shipment['label']->receipt->auth_code     = $receipt_account->{'auth-code'};
                                $shipment['label']->receipt->auth_timestamp= $receipt_account->{'auth-timestamp'};
                                $shipment['label']->receipt->supplier_id   = $receipt_account->{'supplier-id'};
                                $shipment['label']->receipt->charge_amount = $receipt_account->{'charge-amount'};
                                $shipment['label']->receipt->currency      = $receipt_account->{'currency'};
                            }
                        }
	                    if ($xml->{'messages'} && ($this->options->shipment_log)) {
	                        $apierror = '';
	                        $messages = $xml->{'messages'}->children('http://www.canadapost.ca/ws/messages');
	                        foreach ( $messages as $message ) {
	                            $apierror .= 'Error Code: ' . $message->code . "\n";
	                            $apierror .= 'Error Msg: ' . $message->description . "\n\n";
	                        }
	                        $this->log->request['apierror'] = $apierror;
	                    }
	                     
	                }
	            } catch (Exception $ex) {
	                // Http request went wrong.
	                if ($this->options->shipment_log){
	                    $this->log->request['error'] = 'Error: ' . $ex->getMessage();
	                }
	            }
	             
	            if ( $this->options->shipment_log ){
	                $this->log->shipment = $shipment;
	                $this->log->datestamp = current_time('timestamp');
	                // Save to transient for 20 minutes.
	                $this->log->request_type = "Contract Shipment Receipt";
	                $this->save_log($this->log);
	            }
	        }
	    } // endif
	    return $shipment['label'];
	}

    /*
	 * Api function for refunding non-contract shipments
	 */
	public function ct_shipment_refund($refund_url, $shipment_email) {
	     
	    $service_ticket = new stdClass();
	     
	    $xmlRequest = new SimpleXMLElement(<<<XML
<?xml version="1.0" encoding="UTF-8"?>
<shipment-refund-request xmlns="http://www.canadapost.ca/ws/shipment-v8">
<email></email>
</shipment-refund-request>
XML
	    );
	    
	    // Add admin email (required)
	    $xmlRequest->email = $shipment_email;
	    
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
	     
	   $username = ($this->options->shipment_mode=='live') ? $this->options->api_user : $this->options->api_dev_user;
	   $password =  ($this->options->shipment_mode=='live') ? $this->options->api_key : $this->options->api_dev_key;
	
	   // REST URL
	   if (strpos($refund_url, 'https://soa-gw.canadapost.ca/')===0){
	       $username = $this->options->api_user;
	       $password = $this->options->api_key;
	       $service_url = $refund_url;
	   }elseif (strpos($refund_url, 'https://ct.soa-gw.canadapost.ca/')===0){
	       $username = $this->options->api_dev_user;
	       $password = $this->options->api_dev_key;
	       $service_url = $refund_url;
	   } else {
	       // invalid url.
	       $message = 'Error: Invalid Url (Must use Canada Post Webservice Url.)' + "\n";
	       $this->log->request['service'] = $message;
	       return '';
	   }
	     
	    try {
	        // Request shipment refund via api.
	        $request_args = array(
	            'method' => 'POST',
	            'httpversion' => apply_filters( 'http_request_version', '1.1' ),
	            'headers' => array( 'Accept' => 'application/vnd.cpc.shipment-v8+xml',
	                'Content-Type' => 'application/vnd.cpc.shipment-v8+xml',
	                'Accept-language' => $service_language,
	                'Authorization' => 'Basic ' . base64_encode( $username . ':' . $password ) ),
	            'body' => $xmlRequest->asXML(),
	            'sslverify' => true,
	            'sslcertificates' => ABSPATH . WPINC . '/certificates/ca-bundle.crt'
	        );
	
	        $response = wp_remote_request($service_url, $request_args);
	
	        if ( is_wp_error( $response ) ) {
	            $this->log->request['http'] = 'Failed. Error: ' . $response->get_error_code() . ": " . $response->get_error_message() . "\n";
	            return;
	        }
	
	        // Retrieve http body
	        $http_response = wp_remote_retrieve_body( $response );
	
	        // Using SimpleXML to parse xml response
	        libxml_use_internal_errors(true);
	        $xml = simplexml_load_string('<root>' . preg_replace('/<\?xml.*\?>/','',$http_response) . '</root>');
	        if (!$xml && ($this->options->shipment_log)) {
	            $errmsg = 'Failed loading XML' . "\n";
	            $errmsg .= $http_response . "\n";
	            foreach(libxml_get_errors() as $error) {
	                $errmsg.= "\t" . $error->message;
	            }
	            $this->log->request['errmsg'] = $errmsg;
	        } else {
	            if ($xml->{'shipment-refund-request-info'} ) {
	
	                $xml_data = $xml->{'shipment-refund-request-info'}->children();
	
	                $service_ticket->date = (string)$xml_data->{'service-ticket-date'};
	                $service_ticket->ticket_id = (string)$xml_data->{'service-ticket-id'};
	                $service_ticket->email = $shipment_email;
	                
	            }
	            if ($xml->{'messages'} && ($this->options->shipment_log)) {
	                $apierror = '';
	                $messages = $xml->{'messages'}->children('http://www.canadapost.ca/ws/messages');
	                foreach ( $messages as $message ) {
	                    $apierror .= 'Error Code: ' . $message->code . "\n";
	                    $apierror .= 'Error Msg: ' . $message->description . "\n\n";
	                }
	                $this->log->request['apierror'] = $apierror;
	            }
	
	        }
	    } catch (Exception $ex) {
	        // Http request went wrong.
	        if ($this->options->shipment_log){
	            $this->log->request['error'] = 'Error: ' . $ex->getMessage();
	        }
	    }
	    
	    if ( $this->options->shipment_log ){
	        $this->log->datestamp = current_time('timestamp');
	        // Save to transient for 20 minutes.
	        $this->log->request_type = "Contract Shipment Refund";
	        $this->save_log($this->log);
	    }
	
	    return $service_ticket;

	}

	
	/*
	 * Api function for retrieving Manifest report/document
	 */
	function get_manifest() {
		// Get Manifest from WebService.
	}
}