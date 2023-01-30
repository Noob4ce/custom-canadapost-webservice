<?php
/*
 Main Canada Post Tracking Class
woocommerce_cpwebservice_tracking.php

Copyright (c) 2013-2022 Jamez Picard

*/
class woocommerce_cpwebservice_tracking extends cpwebservice_tracking
{

    function get_resource($id) {
        return cpwebservice_r::resource($id);
    }
	
    // Provide Tracking Url
    public function tracking_url($pin, $locale) {
        return 'https://www.canadapost-postescanada.ca/track-reperage/'.esc_attr($locale).'#/details/'.esc_attr($pin);
    }
	
    /*
     * Lookup Tracking data with API
     */
	public function lookup_tracking($order_id, $trackingPin, $refresh=false){
		if (!empty($trackingPin)) {
			// Get tracking data
			$tracking = $this->get_tracking_pin($order_id, $trackingPin);
            $trackingData = $this->get_tracking_meta($tracking);
            if (empty($tracking)){
                $tracking = (object) array('pin'=> $trackingPin, 'meta' => array(), 'events'=> array(), 'dateshipped'=>'','datedelivered'=>'');
            }
			
			// If data is older than 4 hrs but less than 1 week, auto-update.
			if (!empty($trackingData) && is_array($trackingData) && isset($trackingData[0]['update-date-time'])){
				$update = intval($trackingData[0]['update-date-time']);
				if ($update > 0){
					$diff = time() - $update;
					if ($diff > 14400 && $diff < 604800){ // More then 4 hrs but less than 7 days in seconds
						$refresh = true;
					}
				}
			}
			
			// Run Live Lookup
			if (empty($trackingData) || $refresh){
	
				// Live Lookup at Canada Post.
				$trackingData = array();
				
				// Options Data
				$username = ($this->options->mode=='live') ? $this->options->api_user : $this->options->api_dev_user;
		        $password = ($this->options->mode=='live') ? $this->options->api_key  : $this->options->api_dev_key;
				
				// REST URL
				$service_url = ($this->options->mode=='live') ? 'https://soa-gw.canadapost.ca/vis/track/pin/{pin}/summary' : 'https://ct.soa-gw.canadapost.ca/vis/track/pin/{pin}/summary'; // dev.  prod:
				
				// Service Language: (English or Francais) sent as Accept-language header with a value of 'fr-CA' or 'en-CA'
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
					// Set tracking number in REST request url
					$service_url = str_replace("{pin}",$trackingPin,$service_url);
                    
					$request_args = array(
					    'method' => 'GET',
					    'httpversion' => apply_filters( 'http_request_version', '1.1' ),
					    'headers' => array( 'Accept' => 'application/vnd.cpc.track+xml',
					        'Accept-language' => $service_language,
					        'Authorization' => 'Basic ' . base64_encode( $username . ':' . $password ) ),
					    'body' => null,
					    'sslverify' => true,
					    'sslcertificates' => ABSPATH . WPINC . '/certificates/ca-bundle.crt'
					);
					$response = wp_remote_request($service_url, $request_args);
						
					if ( is_wp_error( $response ) ) {
					   $message = 'Failed. Error: ' . $response->get_error_code() . ": " . $response->get_error_message() . "\n";
					   if ($this->options->log_enable){
					       $this->log->apierror .= $message;
					   }
					} else {
					
					// Retrieve http body
					$http_response = wp_remote_retrieve_body( $response );
					
					// Using SimpleXML to parse xml response
					libxml_use_internal_errors(true);
					$xml = simplexml_load_string($http_response);
					if (!$xml && $this->options->log_enable) {
						$this->log->apierror .= 'Failed loading XML' . "\n";
						$this->log->apierror .= $http_response . "\n";
						foreach(libxml_get_errors() as $error) {
							$this->log->apierror .= "\t" . $error->message;
						}
					} else if ($xml) {
						
						$trackingSummary = $xml->children('http://www.canadapost.ca/ws/track');
						if ( $trackingSummary->{'pin-summary'} ) {
							
							foreach ( $trackingSummary as $pinSummary ) {
								$row = array();
								$row['pin'] = (string)$pinSummary->{'pin'};
								$row['mailed-on-date'] =  (string)$pinSummary->{'mailed-on-date'};
								$row['event-description'] =  (string)$pinSummary->{'event-description'};
								$row['origin-postal-id'] =  (string)$pinSummary->{'origin-postal-id'};
								$row['destination-postal-id'] =  (string)$pinSummary->{'destination-postal-id'};
								$row['destination-province'] = (string) $pinSummary->{'destination-province'};
								$row['service-name'] =  (string)$pinSummary->{'service-name'};
								$row['expected-delivery-date'] =  (string)$pinSummary->{'expected-delivery-date'};
								$row['actual-delivery-date'] = (string) $pinSummary->{'actual-delivery-date'};
								$row['event-date-time'] = $this->format_cp_time((string) $pinSummary->{'event-date-time'});
								$row['attempted-date'] = (string) $pinSummary->{'attempted-date'};
								$row['customer-ref-1'] =  (string)$pinSummary->{'customer-ref-1'};
								$row['event-type'] =  (string)$pinSummary->{'event-type'};
								$row['event-location'] =  (string)$pinSummary->{'event-location'};
                                $row['update-date-time'] = time();
                                
                                if (!empty($row['mailed-on-date'])){
                                    $tracking->dateshipped = $row['mailed-on-date'];
                                }
                                if (!empty($row['actual-delivery-date'])){
                                    $tracking->datedelivered = $row['actual-delivery-date'];
                                }
								
								$trackingData[] = $row;
							}
						} else if ($this->options->log_enable) {
							$messages = $xml->children('http://www.canadapost.ca/ws/messages');
							foreach ( $messages as $message ) {
								$this->log->apierror .= 'Error Code: ' . $message->code . "\n";
								$this->log->apierror .= 'Error Msg: ' . $message->description . "\n\n";
							}
						}
					  } else {
						// No tracking available for that pin.
				      }
					} // endif
				} catch (Exception $ex) {
						// Http request went wrong.
						if ($this->options->log_enable){
							$this->log->apierror .= 'Error: ' . $ex; 
						}
				}
				
				// Debug:
				// echo 'API: '.$this->log->apierror; 
				
				if (empty($trackingData)){
					// No tracking was available. just save pin so that this can be displayed to user/and/or able to be removed.
					$row['pin'] = $trackingPin;
					$trackingData[] = $row;
                }
                $tracking->pin = $trackingPin;
                $tracking->meta = $trackingData;
			
                // Save tracking data
                $this->save_tracking($order_id, $tracking->pin, $tracking->meta, $tracking->events, $tracking->dateshipped, $tracking->datedelivered);
			}
			
			return $trackingData;

		}
		
		return array();
	}
        
    /*
	 * Format Time.
	 */
	public function format_cp_time($datetime){
		// format: 20130703:175923
		if (!empty($datetime) && strlen($datetime)>13){
			$d = substr($datetime,0,4).'-'.substr($datetime,4,2).'-'.substr($datetime,6,2);
			$d .=  ' ' .substr($datetime,9,2).':'.substr($datetime,11,2);
			return $d; //date("m/d/Y",strtotime($d));
		}		
		return $datetime;
    }
}