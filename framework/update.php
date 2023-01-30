<?php
/**
 * Custom Update class
 * Allows plugins to display own versioning
* Based on code by Pippin.
* 
* Copyright (c) 2015-2022 Jamez Picard
*/
abstract class cpwebservice_update {
	public $api_url = null;
	public $api_data = null;
	public $slug = '';
	public $plugin = '';
	public $version = null;
	public $upgrade_token = null;
	public $upgrade_url = null;
	public $api_response = null;
	
	protected $upgrade_domain;
	protected $token_timestamp;
	protected $log = array();
	protected $log_enable = false;

	/**
	 * Class constructor.
	 *
	 *
	 * @param string $_api_url The URL pointing to the custom API endpoint.
	 * @param string $_plugin_file Path to the plugin file.
	 * @param array $_api_data Optional data to send with API calls.
	 * @return void
	 */
	function __construct( $_api_url, $_slug, $_api_data ) {
		$this->api_url =  $_api_url;
		$this->slug = plugin_basename($_slug);
		$this->plugin = plugin_dir_path($_slug);
		$this->plugin_file = $_slug;
		$this->api_data = $_api_data;
		$this->version = $_api_data['version'];
		$this->upgrade_url = $_api_data['upgrade_url'];
		$this->product = $_api_data['product'];
		$site = parse_url(site_url(''));
		$this->upgrade_domain = !empty($site) && is_array($site) && isset($site['host']) ? $site['host'] : site_url('');
		
		// debug
		//set_site_transient( 'update_plugins', null );
		
		// Set up filters
		add_filter( 'pre_set_site_transient_update_plugins', array( &$this, 'pre_set_site_transient_update_plugins_filter' ) );
		add_filter( 'plugins_api', array( &$this, 'plugins_api_filter' ), 10, 3);
		
		// Ajax Activate Upgrades
		add_action('wp_ajax_cpwebservice_upgrades',  array( &$this, 'validate_upgrades' ));
		add_action('wp_ajax_cpwebservice_upgrade_notice_dismiss',  array( &$this, 'dismiss_update_notice' ));
	}	
	
	/*
	 * Return resources
	 */
	abstract function get_resource($id);
	

	
	/**
	 * Check for Updates at the defined API endpoint and modify the update array.
	 *
	 * This function dives into the update api just when Wordpress creates its update array,
	 * then adds a custom API call and injects the custom plugin data retrieved from the API.
	 * It is reassembled from parts of the native Wordpress plugin update code.
	 * See wp-includes/update.php line 121 for the original wp_update_plugins() function.
	 *
	 * @uses api_request()
	 *
	 * @param array $_transient_data Update array build by Wordpress.
	 * @return array Modified update array with custom plugin data.
	 */
	function pre_set_site_transient_update_plugins_filter( $_transient_data ) {

		if( empty( $_transient_data ) ) return $_transient_data;

		if (empty($this->api_response)){
    		// Add cpwebservice plugin data to update array (if version is less)
    		$this->api_response = $this->api_request();
		}
		if( empty($_transient_data->response[$this->slug]) && false !== $this->api_response && is_object( $this->api_response ) ) {
		    
		    $this->api_response->slug = $this->slug;
			// update this plugin version
			$this->update_version();
			if( version_compare( $this->version, $this->api_response->new_version, '<' ) ){				
				$_transient_data->response[$this->slug] = $this->api_response;
				$this->upgrade_reg();
				if (!empty($this->upgrade_token)){
    				// Add package update
    				$_transient_data->response[$this->slug]->package = $this->get_update_url();
				}
			} else {
				if (isset($_transient_data->response[$this->slug])) 
					unset($_transient_data->response[$this->slug]);
			}
		}
		return $_transient_data;
	}


	/**
	 * Updates information on the "View version x.x details" page with custom data.
	 *
	 * @uses api_request()
	 *
	 * @param mixed $_data
	 * @param string $_action
	 * @param object $_args
	 * @return object $_data
	 */
	function plugins_api_filter( $_data, $_action = '', $_args = null ) {
		if ( ( $_action != 'plugin_information' ) || !isset( $_args->slug ) || ( $_args->slug != $this->slug ) ) return $_data;

		$api_data = array( 'slug' => $this->slug );
		$api_response = $this->api_request( $api_data );
		if ( false !== $api_response ) {
		    $_data = $api_response;
		    $this->upgrade_reg();
		    if (!empty($this->upgrade_token) && is_object($_data)){
    		    // Add package update
    		    $_data->package = $this->get_update_url($this->upgrade_token);
		    }
		}

		return $_data;
	}

	/**
	 * Calls the API and, if successfull, returns the object delivered by the API.
	 *
	 * @uses wp_remote_post()
	 * @uses is_wp_error()
	 *
	 * @param array $_data Parameters for the API action.
	 * @return false||object
	 */
	private function api_request( $_data = null ) {
		
		try {
		  if (!empty($this->api_url) &&  strpos($this->api_url, $this->get_resource('version_url'))===0) {
    		  $request_body = !empty($this->api_data) ? json_encode($this->api_data) : '';
    		  $request = wp_remote_post( $this->api_url, array( 'timeout' => 15, 'ssverify' => true, 'body' => $request_body ) );
    		  if ( !is_wp_error( $request ) ){
    		      // check version
    		      $request = (object)json_decode( wp_remote_retrieve_body( $request ) );
    		      if( $request && isset($request->sections) ){
    			     $request->sections = maybe_unserialize( (array)$request->sections );
    			     // Return valid object
    			     return $request;
    		      } else {
    		          return false;
    		      }
    		  }  // endif
		  } // endif
		  return false;
		} catch (Exception $ex) {
		    return false;
		}
	}
	
	/* gets version from plugin file*/
	private function update_version() {
	    if (empty($this->version)){
    		$info = get_plugin_data($this->plugin_file);
    		$this->version = $info['Version'];
	    }
	}
	
	private function upgrade_reg() {
	    $reg = get_option('woocommerce_cpwebservice_upgrade');
	    if (isset($reg) && is_object($reg) && isset($reg->upgrade_token) && isset($reg->upgrade_domain)){
	        $this->upgrade_token = $reg->upgrade_token;
	        $this->upgrade_domain = $reg->upgrade_domain;
	        $this->token_timestamp = isset($reg->token_timestamp) ? absint($reg->token_timestamp) : 0;
	    }
	}
	
	private function get_update_url( ){
	    if (!empty($this->upgrade_token) && !empty($this->product) && !empty($this->upgrade_domain)) {
	                
	        // Generate based on $update_token
	        // /envato-server/update?ev_action=download&token=&product=&domain=&v=latest
	        return $this->upgrade_url . sprintf('/update?ev_action=download&token=%s&product=%s&domain=%s&v=latest',urlencode($this->upgrade_token),urlencode($this->product),urlencode($this->upgrade_domain));
	    }
	    // no upgrade_token, auto-update is unavailable.
	    return null;
	}
	
	
	/**
	 * Load and generate the template output with ajax
	 */
	public function validate_upgrades() {
	    // Let the backend only access the page
	    if( !is_admin() ) {
	        wp_die( esc_attr__( 'You do not have sufficient permissions to access this page.' ) );
	    }
	    
	    // Check the user privileges
	    if( !current_user_can( 'manage_woocommerce_orders' ) && !current_user_can( 'edit_shop_orders' ) ) {
	        wp_die( esc_attr__( 'You do not have sufficient permissions to access this page.' ) );
	    }
	    
	    // Check the nonce
	    if( empty( $_GET['action'] ) || !check_admin_referer( $_GET['action'] ) ) {
	        wp_die( esc_attr__( 'You do not have sufficient permissions to access this page.' ) );
	    }
	    
	    if(empty( $_POST['licenseid'] )) {
	        wp_die( esc_attr__( 'License Key / Purchase Code is required.' , 'woocommerce-canadapost-webservice' ) . ' response_false ' );
	    }
	    
	    // Nonce.
	    if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'cpwebservice_upgrades' ) ) {
	        wp_die( esc_attr__( 'You do not have sufficient permissions to access this page.' ) );
	    }
	    
	    
	    // Get posted values
	    $licenseid = trim( sanitize_text_field( $_POST['licenseid'] ) );
	    $email = isset($_POST['email']) ? trim( sanitize_text_field( $_POST['email'] ) ) : '';

	    // Do the token request.
	    $result = $this->update_token($licenseid, $email);
	    
	    if ($result == true){
            // Send Success
            self::display_license($licenseid, $email, $this->upgrade_domain);
	    } else {
	        // Failure
	       echo ' response_false ';
	    }
	    // Ajax end.
	    exit;
	}
	
	/*
	 * Updates token and saves to options.
	 */
	private function update_token($licenseid, $email){
	    
	    $request_fields = array('ev_action'=>'license', 'product'=>$this->product, 'licenseid'=>$licenseid,'domain'=>$this->upgrade_domain,'email'=> $email);
	    
	    // Get security token for upgrades with service.
	    try {
	        
	        $request_args = array(
	            'method' => 'POST',
	            'httpversion' => apply_filters( 'http_request_version', '1.1' ),
	            'headers' => array( 'Accept' => 'application/json'),
	            'body' => $request_fields,
	            'sslverify' => true,
	            'sslcertificates' => ABSPATH . WPINC . '/certificates/ca-bundle.crt'
	        );
	        
	        $response = wp_remote_request($this->upgrade_url . '/license', $request_args);
	        
	        if ( is_wp_error( $response ) && $this->log_enable) {
	            $this->log[] = 'Failed. Error: ' . $response->get_error_code() . ": " . $response->get_error_message() . "\n";
	            return false;
	        }
	        
	        // Retrieve http body
	        $http_response = wp_remote_retrieve_body( $response );
	        
	        // Check for security token.
	        $result = !empty($http_response) ? json_decode($http_response, true) : '';
	        
	        if (!empty($result) && !empty($result['token'])){
	            // Save token.
	            $reg = (object)array('upgrade_token'=>$result['token'], 'licenseid'=>$licenseid,'email'=>$email,'upgrade_domain'=>$this->upgrade_domain,'product'=>$this->product,'hide_notice'=>true, 'token_timestamp'=>time());
	            // Save to options.
	            update_option('woocommerce_cpwebservice_upgrade', $reg);
	            $this->upgrade_token = $result['token'];
	            // Return success.
	            return true;
	        }
	        
	    } catch (Exception $ex) {
	        // Http request went wrong.
	        if ($this->log_enable){ $this->log[] = 'Error: ' . $ex; }
	    }

	    return false;
	}
	
	
	public static function display_license($licenseid, $email, $domain) {
	    // Also used on shippingmethod.php, line 778
	    ?>
	    			<p><span class="dashicons dashicons-yes"></span> <?php _e('Automatic updates are active in the Wordpress Admin.', 'woocommerce-canadapost-webservice') ?></p>
						<p><?php _e('License Key / Purchase Code', 'woocommerce-canadapost-webservice')?>:</p>
						<p><?php echo esc_html(!empty($licenseid) ? $licenseid : '') ?>
						<p><?php _e('Email', 'woocommerce-canadapost-webservice')?>:</p>   
						<p><?php echo esc_html(!empty($email) ? $email: '') ?>
						<p><?php _e('Domain','woocommerce-canadapost-webservice')?>:</p>
						<p><?php echo esc_html(!empty($domain) ? $domain : '') ?></p>
						<p>&nbsp;</p>
						<div><a href="javascript:;" id="btn_cpwebservice_license_refresh" class="button-secondary"><?php _e('Modify','woocommerce-canadapost-webservice'); ?></a></div>
	    <?php 
	}
	
	
	public static function display_update_notice($title){
	    $reg = get_option('woocommerce_cpwebservice_upgrade');
	    if (!$reg || (isset($reg) && is_object($reg) && isset($reg->notice) && $reg->hide_notice) ):
	    ?>
	    <div id="cpwebservice_update_notice">
	    <a href="<?php echo wp_nonce_url( admin_url( 'admin-ajax.php?action=cpwebservice_upgrade_notice_dismiss' ), 'cpwebservice_upgrade_notice_dismiss' ); ?>" class="button button-secondary canadapost-notice-close"><span class="dashicons dashicons-no"></span></a>
	    <p> <?php _e('You can enable automatic plugin updates for ', 'woocommerce-canadapost-webservice') ?> <?php echo esc_html($title)?>, <a href="#cpwebservice_update" class="cpwebservice_update_notice_link"><?php _e('Simply add your Envato license / purchase code','woocommerce-canadapost-webservice')?></a> . <?php _e('Thanks!', 'woocommerce-canadapost-webservice')?>
	    </p>
	    </div> 
	    <?php 
	    endif;
	}
	
	public function dismiss_update_notice(){
	    
	    //Ajax Request.
	    if( !is_admin() ) {
	        wp_die( esc_attr__( 'You do not have sufficient permissions to access this page.' ) );
	    }
	    
	    // Check the user privileges
	    if( !current_user_can( 'manage_woocommerce_orders' ) && !current_user_can( 'edit_shop_orders' ) ) {
	        wp_die( esc_attr__( 'You do not have sufficient permissions to access this page.' ) );
	    }
	    
	    // Check the nonce
	    if( empty( $_GET['action'] ) || !check_admin_referer( $_GET['action'] ) ) {
	        wp_die( esc_attr__( 'You do not have sufficient permissions to access this page.' ) );
	    }
	    // Nonce.
	    if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'cpwebservice_upgrade_notice_dismiss' ) ) {
	        wp_die( esc_attr__( 'You do not have sufficient permissions to access this page.' ) );
	    }
	    
	    // Now do function.
	    
	    $reg = get_option('woocommerce_cpwebservice_upgrade');
	    if (!isset($reg) || !is_object($reg)){
	        $reg = new stdClass();
	    }
	    $reg->hide_notice = true;
	    update_option('woocommerce_cpwebservice_upgrade', $reg);
	    
	    echo 'true';
	    exit;
	}

}