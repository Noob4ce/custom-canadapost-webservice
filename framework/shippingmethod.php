<?php
/*
 Main Shipping Method Webservice Class
 woocommerce_cpwebservice.php

Copyright (c) 2013-2022 Jamez Picard

*/
abstract class cpwebservice_shippingmethod extends WC_Shipping_Method
{	

    /**
     * __construct function.
     *
     * @access public
     * @return woocommerce_cpwebservice
     */
    function __construct($instance_id = 0) {
        
        $this->init($instance_id);
    }
    
    /* Instance id */
    public $instance_id = 0;
    /** logging */
    public $log;
    protected $upgrade;
    
    /** options */
    public $options;
    
    /** boxes */
    public $boxes;
    /** services array */
    public $services;
    public $available_services;
    public $lettermail;
    
    // Service array data
    protected $service_groups;
    protected $service_boxes;
    protected $service_descriptions;
    protected $service_labels;
    protected $commercial_services;
    // Data
    public $shipment_address;
    public $rules;    
    public $packagetypes;
    
    /**
     * init function.
     *
     * @access public
     * @return void
     */
    function init($instance_id = 0) {
        $this->id			      = 'woocommerce_cpwebservice';
        $this->instance_id        = absint( $instance_id );
        $this->method_title 	  = $this->get_resource('method_title');
        $this->method_description = $this->get_resource('method_description');
        $this->supports           = array('shipping-zones', 'settings', 'instance-settings'); // 'instance-settings-modal' 
         
        $default_options = (object) array('enabled'=>'no', 'title'=>$this->method_title, 'api_user'=>'', 'api_key'=>'','account'=>'','contractid'=>'','source_postalcode'=>'','mode'=>'live', 'prefer_service'=>false, 'geolocate_origin'=>true, 'geolocate_limit'=>false, 'packagetype'=>'', 'packing_method'=>'', 'max_box'=> false, 'limit_rates'=> '', 'limit_prefer_delivery'=> false, 'limit_same_delivery'=> false,
            'delivery'=>'', 'delivery_guarantee'=>false, 'delivery_label'=>'', 'margin'=>'', 'margin_value'=>'', 'service_margin'=>array(), 'exchange_rate'=>'', 'packageweight'=>floatval('0'), 'boxes_enable'=> false, 'boxes_switch'=>true, 'lettermail_enable'=> false, 'altrates_enable'=> false, 'rules_enable'=>false, 'volumetric_weight'=>true, 'product_shipping_options' => true, 'weight_only_enabled'=> true,
            'shipping_tracking'=> true, 'email_tracking'=> true, 'log_enable'=>false,'lettermail_limits'=>false,'lettermail_maxlength'=>'','lettermail_maxwidth'=>'','lettermail_maxheight'=>'','lettermail_override_weight'=>false,'lettermail_packageweight'=>'', 'lettermail_exclude_tax'=> false, 'tracking_icons'=> true, 'tracking_action_email'=>'', 'tracking_action_customer'=>'', 'tracking_dateformat' => '',
            'display_required_notice'=>true, 'shipments_enabled'=> true, 'shipment_mode' => 'dev', 'shipment_log'=>false, 'api_dev_user'=>'', 'api_dev_key'=>'', 'display_units'=>'cm', 'display_weights'=>'kg', 'delivery_format'=> '', 'availability'=>'', 'availability_countries'=>'', 'tracking_template'=>'', 'template_package'=>true,'template_customs'=>true,'shipment_hscodes'=>false,'legacytracking'=>true,
            'altrates_defaults' => false, 'altrates_weight' => '','altrates_length' => '','altrates_width' => '','altrates_height' => '', 'legacyshipping'=>true, 'tracking_schedule_time'=>'', 'tracking_heading'=>''
        );
        $default_options->volumetric_weight = $this->get_resource('volumetric_weight_default');
        $this->options		  = get_option('woocommerce_cpwebservice', $default_options);
        $this->options		  =	(object) array_merge((array) $default_options, (array) $this->options); // ensure all keys exist, as defined in default_options.
        $this->enabled		  = $this->options->enabled;
        $this->title 		  = $this->options->title;
        $this->boxes		  = get_option('woocommerce_cpwebservice_boxes');
        $this->services		  = get_option('woocommerce_cpwebservice_services', array());
        $this->lettermail	  = get_option('woocommerce_cpwebservice_lettermail', array());
        $this->shipment_address=get_option('woocommerce_cpwebservice_shipment_address', array());
        $this->rules		  = get_option('woocommerce_cpwebservice_rules', array());
        $this->service_labels = get_option('woocommerce_cpwebservice_service_labels', array());
        $this->packagetypes   = array();
        $this->log 			  = (object) array('cart'=>array(),'params'=>array(),'request'=>array('http'=>'','service'=>''),'rates'=>array(),'info'=>array());
        $this->upgrade        = get_option('woocommerce_cpwebservice_upgrade');
        // Display Units (only in/lb and cm/kg supported).
        $this->options->display_units  = get_option( 'woocommerce_dimension_unit' ); if (empty($this->options->display_units)) { $this->options->display_units = 'cm'; }
        $this->options->display_weights= get_option( 'woocommerce_weight_unit' ); if (empty($this->options->display_weights)) { $this->options->display_weights= 'kg'; }
        $this->options->exchange_rate  = apply_filters('cpwebservice_exchange_rate', $this->options->exchange_rate);
        // Filter to set packing method (Boxpacking is default)
        $this->options->packing_method = apply_filters('cpwebservice_packing_method', $this->options->packing_method);
        $this->options->boxes_switch = apply_filters('cpwebservice_boxes_switch', $this->options->boxes_switch);
        // Deprecated
        $this->availability   = $this->options->availability; // used by parent class WC_Shipping_Method.is_available( array $package )
        $this->countries      = !empty($this->options->availability_countries) ? explode(',', $this->options->availability_countries) : array();
        $this->commercial_services = array();
        // Defined Services
        $this->init_available_services();
    
        // Actions
        add_action('woocommerce_update_options_shipping_' . $this->id, array(&$this, 'process_admin_options'));
        // WPML Method labels
        if (apply_filters( 'cpwebservice_wpml_method_labels', true )){
            $this->wpml_woocommerce_init();
        }
        // Admin only-scripts
        if (is_admin()){
            wp_enqueue_script( 'cpwebservice_admin_settings' ,plugins_url( 'framework/lib/admin-settings.js' , dirname(__FILE__) ) , array( 'jquery' ),  $this->get_resource('version') );
            wp_localize_script( 'cpwebservice_admin_settings', 'cpwebservice_admin_settings', array( 'confirm'=>__('Are you sure you wish to delete?', 'woocommerce-canadapost-webservice') ) );    
            wp_enqueue_style( 'cpwebservice_woocommerce_admin' , plugins_url( 'framework/lib/admin.css' , dirname(__FILE__) ), null,  $this->get_resource('version') );
        }
    }
    
    /*
     * Return resources
     */
    abstract function get_resource($id);
    
    /*
     * Defined Services
     * Populate $this->available_services array.
    */
    abstract public function init_available_services();
    
    /*
     * Return destination Label (ie. Canada, USA, International) from Service code.
    */
    abstract public function get_destination_from_service($service_code);
    
    /*
     * Return 2-char Country Code (CA, US, ZZ) ZZ is international from Service code.
     */
    abstract public function get_destination_country_code_from_service($service_code);
    
    function admin_options() {
        ?>
    		<?php // security nonce
    		  wp_nonce_field(plugin_basename(__FILE__), 'cpwebservice_options_noncename'); 
    		?>
    		<h3><?php echo esc_html($this->get_resource('method_title')) ?></h3>
    		<div><img src="<?php echo esc_attr(plugins_url( $this->get_resource('method_logo_url') , dirname(__FILE__) )) ?>" /></div>
    	    <h2 id="cpwebservice_tabs" class="nav-tab-wrapper woo-nav-tab-wrapper">
			<a href="#cpwebservice_settings" class="nav-tab nav-tab-active" id="cpwebservice_settings_tab"><?php esc_html_e('Settings', 'woocommerce-canadapost-webservice') ?></a>
			<a href="#cpwebservice_services" class="nav-tab" id="cpwebservice_services_tab"><?php esc_html_e('Shipping Rates / Boxes', 'woocommerce-canadapost-webservice') ?></a>
			<a href="#cpwebservice_flatrates" class="nav-tab" id="cpwebservice_flatrates_tab"><?php esc_html_e('Lettermail/Flat Rates', 'woocommerce-canadapost-webservice') ?></a>
			<a href="#cpwebservice_shipments" class="nav-tab <?php echo esc_attr($this->get_resource('display_shipmentstab')); ?>" id="cpwebservice_shipments_tab"><?php esc_html_e('Shipment Labels', 'woocommerce-canadapost-webservice') ?></a>
			<a href="#cpwebservice_tracking" class="nav-tab" id="cpwebservice_tracking_tab"><?php esc_html_e('Tracking', 'woocommerce-canadapost-webservice') ?></a>
			<a href="#cpwebservice_update" class="nav-tab" id="cpwebservice_update_tab"><?php esc_html_e('Updates', 'woocommerce-canadapost-webservice') ?></a>
			</h2>
			<div class="cpwebservice_panel" id="cpwebservice_settings">
			<h3><?php echo esc_html($this->get_resource('method_title').' '.__('Settings', 'woocommerce-canadapost-webservice')) ?></h3>
			<?php cpwebservice_update::display_update_notice($this->get_resource('method_title')); ?>
			<table class="form-table">
				<tr valign="top">
					<th scope="row" class="titledesc"><?php esc_html_e('Enable/Disable', 'woocommerce-canadapost-webservice') ?></th>
					<td class="forminp">
						<fieldset><legend class="screen-reader-text"><span><?php esc_html_e('Enable/Disable', 'woocommerce-canadapost-webservice') ?></span></legend>
								<label for="woocommerce_cpwebservice_enabled">
								<input name="woocommerce_cpwebservice_enabled" id="woocommerce_cpwebservice_enabled" type="checkbox" value="1" <?php checked($this->options->enabled=='yes'); ?> /> <?php esc_html_e(sprintf(__('Enable %s Webservice', 'woocommerce-canadapost-webservice'), $this->get_resource('method_title'))) ?></label><br />
							</fieldset>
						<fieldset class="<?php echo esc_attr($this->get_resource('display_shipmentstab'))?>"><legend class="screen-reader-text"><span><?php esc_html_e('Enable/Disable', 'woocommerce-canadapost-webservice') ?></span></legend>
								<label for="woocommerce_cpwebservice_shipments_enabled">
								<input name="woocommerce_cpwebservice_shipments_enabled" id="woocommerce_cpwebservice_shipments_enabled" type="checkbox" value="1" <?php checked($this->options->shipments_enabled==true); ?> /> <?php esc_html_e('Enable Creation of Shipment Labels', 'woocommerce-canadapost-webservice') ?></label><br />
							</fieldset>
						<fieldset>
						<label for="woocommerce_cpwebservice_shipping_tracking"><input name="woocommerce_cpwebservice_shipping_tracking" id="woocommerce_cpwebservice_shipping_tracking" type="checkbox" value="1" <?php checked($this->options->shipping_tracking==true); ?>  /> <?php esc_html_e(sprintf(__('Enable %s Tracking number feature on Orders', 'woocommerce-canadapost-webservice'), $this->get_resource('method_title'))) ?></label><br />
						</fieldset>
					</td>
				    </tr>
				    <tr valign="top">
					<th scope="row" class="titledesc"><?php esc_html_e('Method Title', 'woocommerce-canadapost-webservice') ?></th>
					<td class="forminp">
						<input type="text" name="woocommerce_cpwebservice_title" id="woocommerce_cpwebservice_title" style="min-width:50px;" value="<?php echo esc_attr($this->options->title); ?>" /> <span class="description"><?php esc_html_e('This controls the title which the user sees during checkout.', 'woocommerce-canadapost-webservice') ?></span>
					</td>
				    </tr>
				    <tr valign="top">
					<th scope="row" class="titledesc"><?php esc_html_e('Webservice Account Settings', 'woocommerce-canadapost-webservice') ?></th>
					<td class="forminp">
					    <div class="<?php echo esc_attr($this->get_resource('display_customeraccount'))?>">
					    <p><strong><?php esc_html_e('Customer Account Number', 'woocommerce-canadapost-webservice') ?>:</strong></p>
						<input type="text" name="woocommerce_cpwebservice_account" id="woocommerce_cpwebservice_account" style="min-width:50px;" value="<?php echo esc_attr($this->options->account); ?>" /> 
                        </div>
						<p><input type="radio" class="woocommerce_cpwebservice_contractid_button" name="woocommerce_cpwebservice_accounttype" id="woocommerce_cpwebservice_accounttype_0" value="0" <?php checked(empty($this->options->contractid)); ?> />
						<label for="woocommerce_cpwebservice_accounttype_0"> <?php esc_html_e('Personal/Small Business Customer','woocommerce-canadapost-webservice')?></label> &nbsp; 
						<input type="radio" class="woocommerce_cpwebservice_contractid_button" name="woocommerce_cpwebservice_accounttype" id="woocommerce_cpwebservice_accounttype_1" value="1" <?php checked(!empty($this->options->contractid)); ?> />
						<label for="woocommerce_cpwebservice_accounttype_1"> <?php esc_html_e('Commercial/Contract Customer','woocommerce-canadapost-webservice')?></label></p>  
						
						<div id="woocommerce_cpwebservice_contractid_display" style="<?php echo (!empty($this->options->contractid) ? "":"display:none"); ?>">
						<input type="text" name="woocommerce_cpwebservice_contractid" id="woocommerce_cpwebservice_contractid" style="min-width:50px;" value="<?php echo esc_attr($this->options->contractid); ?>" /> <span class="description"><?php esc_html_e('Contract ID (Optional, Only if a Contract Customer)', 'woocommerce-canadapost-webservice') ?> <?php echo esc_html($this->get_resource('contract_description')) ?></span>
						<br /></div>
						<p><strong><?php esc_html_e('Production Credentials', 'woocommerce-canadapost-webservice')?></strong></p>
						<input type="text" name="woocommerce_cpwebservice_api_user" id="woocommerce_cpwebservice_api_user" style="min-width:50px;" value="<?php echo esc_attr($this->options->api_user); ?>" /> <span class="description"><?php esc_html_e('API Username', 'woocommerce-canadapost-webservice') ?></span>
						<br />
						<div class="<?php echo esc_attr($this->get_resource('display_apikey'))?>">
						<input type="password" name="woocommerce_cpwebservice_api_key" id="woocommerce_cpwebservice_api_key" style="min-width:50px;" value="<?php echo esc_attr($this->options->api_key); ?>" /> <span class="description"><?php esc_html_e('API Password/Key', 'woocommerce-canadapost-webservice') ?></span>
						<br /></div>
						<div><a href="<?php echo wp_nonce_url( admin_url( 'admin-ajax.php?action=cpwebservice_validate_api_credentials&mode=live' ), 'cpwebservice_validate_api_credentials' ); ?>" id="woocommerce_cpwebservice_validate_btn" class="button canadapost-validate"><?php esc_html_e('Validate Credentials', 'woocommerce-canadapost-webservice') ?></a> <div class="cpwebservice_ajaxupdate canadapost-spinner" style="display: none;"><div class="canadapost-spinner-b1"></div><div class="canadapost-spinner-b2"></div><div class="canadapost-spinner-b3"></div></div><br /></div>							
						<div id="woocommerce_cpwebservice_validate" class="widefat" style="display:none;"><a href="#" class="button button-secondary canadapost-validate-close"><span class="dashicons dashicons-no"></span></a><p></p></div>
					</td>
				    </tr>
				    <tr valign="top" class="woocommerce-canadapost-development-api <?php if($this->options->mode!='dev' && $this->options->shipment_mode!='dev'){ echo 'hidden'; }  ?>">
				    <th><?php esc_html_e('Development', 'woocommerce-canadapost-webservice') ?></th>
				    <td>
				    <p><strong><?php esc_html_e('Development Credentials', 'woocommerce-canadapost-webservice') ?></strong></p>
				    <input type="text" name="woocommerce_cpwebservice_api_dev_user" id="woocommerce_cpwebservice_api_dev_user" style="min-width:50px;" value="<?php echo esc_attr($this->options->api_dev_user); ?>" /> <span class="description"><?php esc_html_e('Development', 'woocommerce-canadapost-webservice') ?> <?php esc_html_e('API Username', 'woocommerce-canadapost-webservice') ?></span>
						<br />
						<div class="<?php echo esc_attr($this->get_resource('display_apikey'))?>">
						<input type="password" name="woocommerce_cpwebservice_api_dev_key" id="woocommerce_cpwebservice_api_dev_key" style="min-width:50px;" value="<?php echo esc_attr($this->options->api_dev_key); ?>" /> <span class="description"><?php esc_html_e('Development', 'woocommerce-canadapost-webservice') ?> <?php esc_html_e('API Password/Key', 'woocommerce-canadapost-webservice') ?></span>
						<br /></div>
						<div><a href="<?php echo wp_nonce_url( admin_url( 'admin-ajax.php?action=cpwebservice_validate_api_credentials&mode=dev' ), 'cpwebservice_validate_api_credentials' ); ?>" id="woocommerce_cpwebservice_validate_dev_btn" class="button canadapost-validate-dev"><?php esc_html_e('Validate Credentials', 'woocommerce-canadapost-webservice') ?></a> <div class="cpwebservice_ajaxupdate_dev canadapost-spinner" style="display: none;"><div class="canadapost-spinner-b1"></div><div class="canadapost-spinner-b2"></div><div class="canadapost-spinner-b3"></div></div><br /></div>							
						<div id="woocommerce_cpwebservice_validate_dev" class="widefat" style="display:none;"><a href="#" class="button button-secondary canadapost-validate-close"><span class="dashicons dashicons-no"></span></a><p></p></div>
				       </td>
				    </tr>
				    <tr valign="top">
				    <th scope="row" class="titledesc"><?php esc_html_e('Webservice API Mode', 'woocommerce-canadapost-webservice') ?></th>
					<td class="forminp">
						<fieldset><legend class="screen-reader-text"><span><?php esc_html_e('Webservice API Mode', 'woocommerce-canadapost-webservice') ?></span></legend>
					  <?php esc_html_e('Rates Lookup / Tracking', 'woocommerce-canadapost-webservice') ?>: &nbsp;
								<select name="woocommerce_cpwebservice_mode" class="canadapost-mode canadapost-mode-rates">
									<option value="dev"<?php if ($this->options->mode=='dev') echo 'selected="selected"'; ?>><?php esc_html_e('Development', 'woocommerce-canadapost-webservice') ?></option>
									<option value="live" <?php if ($this->options->mode=='live') echo 'selected="selected"'; ?>><?php esc_html_e('Production/Live', 'woocommerce-canadapost-webservice') ?></option>
								</select>
								<p class="canadapost-mode-rates-dev-msg <?php if ($this->options->mode!='dev'){ echo ' hidden'; } ?>">
								   <strong><span class="dashicons dashicons-info"></span> <?php esc_html_e('Test Mode', 'woocommerce-canadapost-webservice') ?>:</strong> <?php esc_html_e('Rates will not reflect actual account prices. Tracking disabled.', 'woocommerce-canadapost-webservice')?>
								</p>
								<p class="canadapost-mode-rates-live-msg <?php if ($this->options->mode!='live'){ echo ' hidden'; } ?>">
								   <strong><span class="dashicons dashicons-flag"></span>  <?php esc_html_e('Live Mode', 'woocommerce-canadapost-webservice') ?>:</strong> <?php esc_html_e('Rates reflect your account prices. Package Tracking is available.','woocommerce-canadapost-webservice')?>
								</p>
								</fieldset>
							<br />
								<fieldset class="<?php echo esc_attr($this->get_resource('display_shipmentstab'))?>"><legend class="screen-reader-text"><span><?php esc_html_e('Development Mode', 'woocommerce-canadapost-webservice') ?></span></legend>
								<?php esc_html_e('Shipment Labels', 'woocommerce-canadapost-webservice') ?>: 
								<select name="woocommerce_cpwebservice_shipment_mode" class="canadapost-mode canadapost-mode-shipment">
									<option value="dev"<?php if ($this->options->shipment_mode=='dev') echo 'selected="selected"'; ?>><?php esc_html_e('Development', 'woocommerce-canadapost-webservice') ?></option>
									<option value="live" <?php if ($this->options->shipment_mode=='live') echo 'selected="selected"'; ?>><?php esc_html_e('Production/Live', 'woocommerce-canadapost-webservice') ?></option>
								</select>
								<p class="canadapost-mode-shipment-dev-msg <?php if ($this->options->shipment_mode!='dev'){ echo ' hidden'; } ?>">
								     <strong><span class="dashicons dashicons-info"></span> <?php esc_html_e('Test Mode', 'woocommerce-canadapost-webservice') ?>:</strong> <?php esc_html_e('Only test labels will be created', 'woocommerce-canadapost-webservice')?>.
								 </p>
								<p class="canadapost-mode-shipment-live-msg <?php if ($this->options->shipment_mode!='live'){ echo ' hidden'; } ?>">
								     <strong><span class="dashicons dashicons-flag"></span> <?php esc_html_e('Live Mode', 'woocommerce-canadapost-webservice')?>:</strong> <?php esc_html_e('Paid shipping labels can be created and will be billed to your account', 'woocommerce-canadapost-webservice')?>.
								</p>
								</fieldset>
					</td>
				    </tr>
				    <tr valign="top" class="woocommerce-canadapost-shipment-address">
					<th scope="row" class="titledesc"><?php esc_html_e('Sender Address', 'woocommerce-canadapost-webservice') ?></th>
					<td class="forminp">
						<div id="cpwebservice_address">
						<?php 
						$postal_array = array();
						if (empty($this->shipment_address) || !is_array($this->shipment_address) || count($this->shipment_address) == 0){
						    // Init shipment_address array. (min 1 element)
						    $this->shipment_address = array(array('default'=>true,'postalcode'=>$this->options->source_postalcode,'contact'=>'','phone'=>'', 'address'=>'','address2'=>'','city'=>'','prov'=>'','country'=>'', 'origin'=>true, 'postalcode_lat'=>0, 'postalcode_lng'=>0));
						}
						$address_defaults = array('default'=>false,'contact'=>'','phone'=>'','postalcode'=>'','address'=>'','address2'=>'','city'=>'','prov'=>'','country'=>'', 'origin'=>false, 'postalcode_lat'=>0, 'postalcode_lng'=>0);
						?>
						<?php for($i=0;$i<count($this->shipment_address); $i++): ?>
						<?php $address = (is_array($this->shipment_address[$i]) ? array_merge($address_defaults, $this->shipment_address[$i]) : array()); ?>
						 <div class="cpwebservice_address_item">
						 <h4 class="titledescr"> <?php esc_html_e('Sender Address', 'woocommerce-canadapost-webservice') ?></h4>
						 <span style="float:right;" class="canadapost-remove-btn<?php if ($i==0):?> hidden<?php endif;?>"><a href="javascript:;" title="Remove" class="canadapost-address-remove button"><?php esc_html_e('Remove','woocommerce-canadapost-webservice'); ?></a></span>
						 <span class="description"><?php esc_html_e('Source address to be used for Rates lookup and Sender Address to be printed on Shipment label', 'woocommerce-canadapost-webservice') ?></span>
						 <br />
						 <br />
						 <p><strong><?php esc_html_e('Sender Zip/Postal Code', 'woocommerce-canadapost-webservice') ?></strong> <span class="description">(*<?php esc_html_e('Required', 'woocommerce-canadapost-webservice') ?>)</span></p>
						 <input type="text" name="woocommerce_cpwebservice_shipment_postalcode[]" id="woocommerce_cpwebservice_shipment_postalcode<?php echo esc_attr($i);?>" class="canadapost-shipment-postal" data-postaltype="<?php echo esc_attr($this->get_resource('origin_postal_format')); ?>" style="min-width:50px;" placeholder="<?php echo esc_attr($this->get_resource('origin_postal_placeholder')); ?>" value="<?php echo esc_attr($address['postalcode']); ?>" /> 
						 <?php if (!empty($address['postalcode_lat']) && !empty($address['postalcode_lng'])) : ?><span class="description canadapost-hide-new"><?php esc_html_e('Approximate Latitude/Longitude','woocommerce-canadapost-webservice') ?>: (<?php echo esc_html($address['postalcode_lat']) ?>,<?php echo esc_html($address['postalcode_lng']) ?>)</span><?php endif; ?>
						 <div id="woocommerce_cpwebservice_shipment_postalcode<?php echo esc_attr($i);?>_error" style="display:none;background-color: #fffbcc;padding:5px;border-color: #e6db55;"><p><?php echo esc_html($this->get_resource('postalcode_warning')); ?></p></div>
						 <div class="canadapost-postal-requires-one" style="display:none;background-color: #fffbcc;padding:5px;border-color: #e6db55;"><p><?php esc_html_e('Rates lookup require an Origin Postal Code', 'woocommerce-canadapost-webservice') ?>.</p></div>
						 <?php if (count($this->shipment_address) == 1) { $address['origin'] = true; } ?>
						 <br /><label class="canadapost-postalcode-origin-label"><input name="woocommerce_cpwebservice_shipment_postalcode_origin[]" type="checkbox" value="<?php echo esc_attr($i);?>" <?php checked($address['origin']==true); ?> class="canadapost-postalcode-origin"  /> <strong><?php esc_html_e('Use as Origin Postal Code for Rates Lookup', 'woocommerce-canadapost-webservice') ?></strong></label>
						 <br />
						 <br />
						<?php esc_html_e('Sender Contact Name/Company', 'woocommerce-canadapost-webservice') ?><br />
						<input type="text" name="woocommerce_cpwebservice_shipment_contact[]" id="woocommerce_cpwebservice_shipment_contact<?php echo esc_attr($i);?>" style="min-width:50px;" value="<?php echo esc_attr($address['contact']); ?>" /> 
						<br />
						<?php esc_html_e('Contact Phone', 'woocommerce-canadapost-webservice') ?><br />
						<input type="text" name="woocommerce_cpwebservice_shipment_phone[]" id="woocommerce_cpwebservice_shipment_phone<?php echo esc_attr($i);?>" style="min-width:50px;" value="<?php echo esc_attr($address['phone']); ?>" />
						<br />
						 <?php esc_html_e('Address', 'woocommerce-canadapost-webservice') ?><br />
						 <input type="text" name="woocommerce_cpwebservice_shipment_address[]" id="woocommerce_cpwebservice_shipment_address<?php esc_attr($i);?>" style="min-width:50px;" value="<?php echo esc_attr($address['address']); ?>" />
						 <br />
						 <?php esc_html_e('Address2', 'woocommerce-canadapost-webservice') ?><br />
						 <input type="text" name="woocommerce_cpwebservice_shipment_address2[]" id="woocommerce_cpwebservice_shipment_address2<?php esc_attr($i);?>" style="min-width:50px;" value="<?php echo esc_attr($address['address2']); ?>" />
						 <br /> 
						 <?php esc_html_e('City', 'woocommerce-canadapost-webservice') ?><br />
						 <input type="text" name="woocommerce_cpwebservice_shipment_city[]" id="woocommerce_cpwebservice_shipment_city<?php echo esc_attr($i);?>" style="min-width:50px;" value="<?php echo esc_attr($address['city']); ?>" />
						 <br /> 
						 <?php 
						 $address['country'] = (!empty($address['country']) && in_array($address['country'], array_keys($this->get_resource('sender_shipment_countries')))) ? $address['country'] : $this->get_resource('shipment_country');
						 $shipment_states =  WC()->countries->get_states( $address['country'] );
						 ?>
						 <span class="canadapost-shipment-prov-label" <?php if (empty($shipment_states)) { echo 'style="display:none"'; } ?>>
						 <?php esc_html_e('State/Province', 'woocommerce-canadapost-webservice') ?><br />
						 </span>
						 <select name="woocommerce_cpwebservice_shipment_prov[]" id="woocommerce_cpwebservice_shipment_prov<?php echo esc_attr($i);?>" <?php if (empty($shipment_states)) { echo 'style="display:none"'; } ?> class="canadapost-shipment-prov">
						    <option value="" <?php selected( '', esc_attr( $address['prov'] ) ); ?>></option>
						    <?php
    						  foreach ( (array) $shipment_states as $option_key => $option_value ) : ?>
    							<option value="<?php echo esc_attr( $option_key ); ?>" <?php selected( $option_key, esc_attr( $address['prov'] ) ); ?>><?php echo esc_attr( $option_value ); ?></option>
    						<?php endforeach; ?>
    					</select>
    					<br />
						 <?php esc_html_e('Country', 'woocommerce-canadapost-webservice') ?><br />
						  <select name="woocommerce_cpwebservice_shipment_country[]" id="woocommerce_cpwebservice_shipment_country<?php echo esc_attr($i);?>" class="canadapost-shipment-country">
						      <?php foreach ($this->get_resource('sender_shipment_countries') as $sender_country=>$sender_country_label) : ?>
						      <option value="<?php echo esc_attr($sender_country) ?>" <?php selected( $sender_country, esc_attr( $address['country'] ) ); ?>><?php echo esc_html($sender_country_label) ?></option>
						      <?php endforeach; ?>
						  </select>
						 <br />
						 <br />
						 <label><input type="radio" name="woocommerce_cpwebservice_shipment_default" value="<?php echo esc_attr($i); ?>" <?php checked(true,$address['default'])?> /><?php esc_html_e('Default Sending Address', 'woocommerce-canadapost-webservice'); ?></label>
						 <br />
						 <br />
						  </div> <?php if ($address['origin']){ $postal_array[] = $address['postalcode']; } ?>
						  <?php endfor; ?>
						  </div>
						  <a href="javascript:;" id="btn_cpwebservice_address" class="button-secondary"><?php esc_html_e('Add More','woocommerce-canadapost-webservice'); ?> <span class="dashicons dashicons-plus-alt" style="margin-top:5px;"></span></a>
					</td>
				    </tr>
				    <tr valign="top">
					<th scope="row" class="titledesc"><?php esc_html_e('Origin Postal Code', 'woocommerce-canadapost-webservice') ?></th>
					<td class="forminp">
					   <div class="canadapost-display-geolocate <?php if (count($this->shipment_address) == 1){ echo 'hidden'; } ?>">
					   <label><input name="woocommerce_cpwebservice_geolocate_origin" type="checkbox" value="1" <?php checked(true,$this->options->geolocate_origin); ?>  /><?php esc_html_e('Use multiple Origin Postal Codes for Rates Lookup', 'woocommerce-canadapost-webservice') ?></label>
					   <br />
					   <p class="description"><?php echo esc_html(__('This will use the "closest" origin postal code to the entered postal code when looking up rates. If the approximate lat/long for the postal code cannot be found, it will use the "Default Sending Address" Postal code.')) ?></p>
					   <label><input name="woocommerce_cpwebservice_geolocate_limit" type="checkbox" value="1" <?php checked(true,$this->options->geolocate_limit); ?>  /><?php esc_html_e('Limit Sending Address/Warehouses on Products (Enables selection on Product Edit page)', 'woocommerce-canadapost-webservice') ?></label>
					   </div>
					   <br />
					   <p><?php esc_html_e('Currently using the following Postal Code(s) as Origins when looking up rates')?>: <strong><span class="canadapost-postal-array"><?php echo esc_html(implode(',', $postal_array));?></span></strong></p>
					<div class="hidden">
						<input type="text" name="woocommerce_cpwebservice_source_postalcode" id="woocommerce_cpwebservice_source_postalcode" style="min-width:50px;" class="canadapost-postal" value="<?php echo esc_attr($this->options->source_postalcode); ?>" /> <span class="description"><?php esc_html_e('The Postal Code that items will be shipped from.', 'woocommerce-canadapost-webservice') ?></span>
						<div class="canadapost-postal-error" style="display:none;background-color: #fffbcc;padding:5px;border-color: #e6db55;"><p><?php echo esc_html($this->get_resource('postalcode_warning')); ?></p></div>
				    </div>
					</td>
				    </tr>
				  </table>
				  <table class="form-table">
				
				</table>
		</div> <!-- /#cpwebservice_settings -->
		
		<div class="cpwebservice_panel cpwebservice_hidden" id="cpwebservice_services">
		<table class="form-table"> 
		 <tr><td colspan="2" style="padding-left:0;border-bottom: 1px solid #999;">
		                 <h3><?php esc_html_e('Rates Lookup', 'woocommerce-canadapost-webservice') ?></h3>
				    </td></tr>
				     <tr valign="top">
				    <th scope="row" class="titledesc">
				    	<?php esc_html_e('Logging', 'woocommerce-canadapost-webservice')?>
				    </th>
					<td class="forminp">
					<label for="woocommerce_cpwebservice_log_enable">
								<input name="woocommerce_cpwebservice_log_enable" id="woocommerce_cpwebservice_log_enable" type="checkbox" value="1" <?php checked($this->options->log_enable); ?> /> <?php esc_html_e('Enable Rates Lookup Logging', 'woocommerce-canadapost-webservice') ?>
								<br /><small><?php esc_html_e('Captures most recent shipping rate lookup.  Recommended to be disabled when website development is complete. This option does not display any messages on frontend.', 'woocommerce-canadapost-webservice') ?></small></label>
					<?php if ($this->options->log_enable): ?>
					<div><a href="<?php echo wp_nonce_url( admin_url( 'admin-ajax.php?action=cpwebservice_rates_log_display' ), 'cpwebservice_rates_log_display' ); ?>" title="Display Log" class="button canadapost-log-display"><?php esc_html_e('Display most recent request','woocommerce-canadapost-webservice'); ?></a> 
					<div class="canadapost-spinner canadapost-log-display-loading" style="display: none;"><div class="canadapost-spinner-b1"></div><div class="canadapost-spinner-b2"></div><div class="canadapost-spinner-b3"></div></div>
					<a href="#" class="button button-secondary canadapost-log-close" style="display:none"><span class="dashicons dashicons-no"></span></a>
					</div>
					<div id="cpwebservice_log_display" style="display:none;">
					<p></p>
					</div>
					<?php endif; ?> 
					</td>
					</tr>
					<tr>
					<th scope="row" class="titledesc"><?php esc_html_e('Exchange Rate', 'woocommerce-canadapost-webservice') ?></th>
					<td class="forminp">
					   <p class="description"><?php echo esc_html(__('Currency Conversion (ex. CAD to USD)','woocommerce-canadapost-webservice'))?>. 
					   <?php esc_html_e('Woocommerce Currency', 'woocommerce-canadapost-webservice') ?>: <?php echo esc_html(get_woocommerce_currency()); ?>. 
					       <br /><?php echo esc_html($this->get_resource('margin_currency')) ?></p>
						   <input type="text" name="woocommerce_cpwebservice_exchange_rate" id="woocommerce_cpwebservice_exchange_rate" style="max-width:50px;" value="<?php echo esc_attr($this->options->exchange_rate); ?>" /> <span class="description"><?php esc_html_e('Exchange Rate (Percentage. ex. 0.85). It adjusts the Shipping Cost by multiplying by the rate.', 'woocommerce-canadapost-webservice') ?></span>
					</td>
					</tr>
				    <tr>
					<th scope="row" class="titledesc"><?php esc_html_e('Add Margin', 'woocommerce-canadapost-webservice') ?></th>
					<td class="forminp">
					   <p class="description"><?php echo esc_html(__('Margin can be % or Amount to adjust the shipping rate','woocommerce-canadapost-webservice'))?>.</p>
							&nbsp; <input type="text" name="woocommerce_cpwebservice_margin" id="woocommerce_cpwebservice_margin" style="max-width:50px;" value="<?php echo esc_attr($this->options->margin); ?>" />% <span class="description"><?php esc_html_e('Add Margin Percentage (ex. 5% or -2%) to Shipping Cost', 'woocommerce-canadapost-webservice') ?></span><br />
							$<input type="text" name="woocommerce_cpwebservice_margin_value" id="woocommerce_cpwebservice_margin_value" style="max-width:50px;" value="<?php echo esc_attr($this->options->margin_value); ?>" /> &nbsp; <span class="description"><?php esc_html_e('Add Margin Amount (ex. $4 or -$1) to Shipping Cost', 'woocommerce-canadapost-webservice') ?></span>
					</td>
				    </tr>
				    <tr>
					<th scope="row" class="titledesc"><?php esc_html_e('Delivery Dates', 'woocommerce-canadapost-webservice') ?></th>
					<td class="forminp">
								<p><label for="woocommerce_cpwebservice_delivery_hide">
								<input name="woocommerce_cpwebservice_delivery_hide" id="woocommerce_cpwebservice_delivery_hide" type="checkbox" value="1" <?php checked(!empty($this->options->delivery)); ?> /> <?php esc_html_e('Enable Estimated Delivery Dates', 'woocommerce-canadapost-webservice') ?></label>
								</p>
								<p><label for="woocommerce_cpwebservice_delivery_guarantee"><input name="woocommerce_cpwebservice_delivery_guarantee" id="woocommerce_cpwebservice_delivery_guarantee" type="checkbox" value="1" <?php checked(!empty($this->options->delivery_guarantee)); ?> /> <?php esc_html_e('Show Estimated Delivery only on Guaranteed Services', 'woocommerce-canadapost-webservice') ?></label> <span class="description"><?php echo esc_html($this->get_resource('guaranteed_services')) ?></span></p>
								<p><input type="text" name="woocommerce_cpwebservice_delivery" id="woocommerce_cpwebservice_delivery" style="max-width:50px;" value="<?php echo esc_attr($this->options->delivery); ?>" /> <span class="description"><?php esc_html_e('Number of Days to Ship after order placed.  Used to calculate date mailed.', 'woocommerce-canadapost-webservice') ?></span></p>
								<p><select name="woocommerce_cpwebservice_delivery_format">
								    <option value="" <?php selected('', $this->options->delivery_format); ?>><?php esc_html_e('Default (ex: 2020-02-29)','woocommerce-canadapost-webservice')?></option>
								    <option value="l M j, Y" <?php selected('l M j, Y', $this->options->delivery_format); ?>><?php esc_html_e('Full Date (ex: Monday Feb 29, 2020)','woocommerce-canadapost-webservice')?></option>
								    <option value="D M j, Y" <?php selected('D M j, Y', $this->options->delivery_format); ?>><?php esc_html_e('Full Date (ex: Mon Feb 29, 2020)','woocommerce-canadapost-webservice')?></option>
								    <option value="D j M Y" <?php selected('D j M Y', $this->options->delivery_format); ?>><?php esc_html_e('Full Date (ex: Mon 29 Feb 2020)','woocommerce-canadapost-webservice')?></option>
								    <option value="F j, Y" <?php selected('F j, Y', $this->options->delivery_format); ?>><?php esc_html_e('Date (ex: February 29, 2020)','woocommerce-canadapost-webservice')?></option>
								    <option value="M j, Y" <?php selected('M j, Y', $this->options->delivery_format); ?>><?php esc_html_e('Date (ex: Feb 29, 2020)','woocommerce-canadapost-webservice')?></option>
								    <option value="M j" <?php selected('M j', $this->options->delivery_format); ?>><?php esc_html_e('Date (ex: Feb 29)','woocommerce-canadapost-webservice')?></option>
								</select> <span class="description"><?php esc_html_e('Date format for Delivery Estimate', 'woocommerce-canadapost-webservice') ?></span></p>
								<p><input type="text" name="woocommerce_cpwebservice_delivery_label" id="woocommerce_cpwebservice_delivery_label" placeholder="<?php esc_html_e('Delivered by', 'woocommerce-canadapost-webservice') ?>" value="<?php echo esc_attr($this->options->delivery_label); ?>" /> <span class="description"><?php esc_html_e('Label for Delivered by', 'woocommerce-canadapost-webservice') ?></span></p>
					</td>
				    </tr>
				    <tr>
				    <th scope="row" class="titledesc"><?php esc_html_e('Validation', 'woocommerce-canadapost-webservice')?> </th>
					<td class="forminp">
							<label for="woocommerce_cpwebservice_display_required_notice">
								<input name="woocommerce_cpwebservice_display_required_notice" id="woocommerce_cpwebservice_display_required_notice" type="checkbox" value="1" <?php checked(!empty($this->options->display_required_notice)); ?> /> <?php esc_html_e('Validate Zip / Postal code as required in Calculate Shipping form', 'woocommerce-canadapost-webservice') ?></label>
					</td>
				    </tr>	
				    <tr><td colspan="2" style="padding-left:0;border-bottom: 1px solid #999;">
		                 <h3><?php esc_html_e('Parcel Services', 'woocommerce-canadapost-webservice') ?></h3>
				    </td>
				    </tr>
				    <?php if (!class_exists('WC_Shipping_Zone')):?>
				    <!-- Legacy (2.4 only) -->
		            <tr>
				    <th scope="row" class="titledesc"><?php esc_html_e('Method Availability', 'woocommerce-canadapost-webservice') ?></th>
					<td class="forminp">
							<select name="woocommerce_cpwebservice_availability">
							     <option value=""><?php esc_html_e('Any Country', 'woocommerce-canadapost-webservice')?></option>
								<option value="including" <?php selected('including' == $this->availability); ?>><?php esc_html_e('Only Selected countries', 'woocommerce-canadapost-webservice') ?></option>
								<option value="excluding" <?php selected('excluding' == $this->availability); ?>><?php esc_html_e('Excluding selected countries', 'woocommerce-canadapost-webservice') ?></option>
						    </select>
					</td>
				    </tr>
				    <tr>
				    <th scope="row" class="titledesc"></th>
					<td class="forminp">
							<select name="woocommerce_cpwebservice_availability_countries[]" class="widefat chosen_select" placeholder="<?php esc_html_e('Choose countries..', 'woocommerce-canadapost-webservice') ?>" multiple>
								<option value=""></option>
								<?php $r_countries = WC()->countries->get_shipping_countries(); ?>
								<?php foreach($r_countries as $country_code=>$name): ?>
								<option value="<?php echo esc_attr($country_code); ?>" <?php selected(is_array($this->countries) && in_array($country_code, $this->countries)); ?>><?php echo esc_html($name); ?></option>
								<?php endforeach;?>
						    </select>
					</td>
				    </tr>
				    <!-- End Legacy (2.4 only) -->
				    <?php endif; ?>
					<th scope="row" class="titledesc"><?php esc_html_e('Enable Parcel Services', 'woocommerce-canadapost-webservice') ?><br /><br />
					<p class="description">
						<small>
							<span class="dashicons dashicons-tag"></span> <?php esc_html_e('Custom Label', 'woocommerce-canadapost-webservice'); ?>
							<span class="woocommerce-help-tip" data-tip="<?php esc_attr_e( 'Change the name of the service that is shown in the cart.', 'woocommerce-canadapost-webservice' ); ?>"></span><br />
							<span class="canadapost-icon-letter">%</span> <?php esc_html_e('Margin', 'woocommerce-canadapost-webservice'); ?>
							<span class="woocommerce-help-tip" data-tip="<?php esc_attr_e( 'Adjust the cost of each service by a margin. You can specify it as a percent if you put a % after the number. Otherwise, it is used as a dollar ($) amount. Ex. -2% and 1.5 are both valid margin adjustments.', 'woocommerce-canadapost-webservice' ); ?>"></span>
						</small>
					</p>
					</th>
					<td class="forminp">
							<fieldset><legend class="screen-reader-text"><span><?php echo esc_html($this->get_resource('method_title') . ' ' . __('Shipping Services', 'woocommerce-canadapost-webservice')) ?></span></legend>
							<?php if (empty($this->services)){ 
							         $this->services = array_keys($this->available_services); // set all checked as default.  
							         $this->services = array_diff($this->services, $this->get_resource('services_default_unselected'));
							      } 
								  $s=0; // service count
								  $cur_country = ' '; ?>
						    <ul>
							<?php foreach($this->available_services as $service_code=>$service_label): ?>
							<?php $s++;
							      $service_country = $this->get_destination_from_service($service_code);
								  if ($cur_country!=$service_country){ $cur_country=$service_country; echo '<li><h4>'.esc_html($service_country).'</h4></li>'; }?>
								<li style="<?php echo esc_attr($this->hide_service($service_code) ? 'display:none' : ''); ?>"><label for="woocommerce_cpwebservice_service-<?php echo esc_attr($s) ?>">
								<input name="woocommerce_cpwebservice_services[]" id="woocommerce_cpwebservice_service-<?php echo esc_attr($s) ?>" type="checkbox" value="<?php echo esc_attr($service_code) ?>" <?php checked(in_array($service_code,$this->services)); ?> /> <?php echo esc_html($service_label); ?></label>
								<?php $custom_service_label = (!empty($this->service_labels) && !empty($this->service_labels[$service_code]) && $this->service_labels[$service_code] != $service_label) ? $this->service_labels[$service_code] : ''; 
								      $custom_service_margin = (!empty($this->options->service_margin) && !empty($this->options->service_margin[$service_code]) && $this->options->service_margin[$service_code] != '0%') ? $this->options->service_margin[$service_code] : ''; ?>
								<a class="button canadapost-btn-icon canadapost-service-label-edit" href="#" style="<?php echo !empty($custom_service_label) ? 'display:none' : ''; ?>" title="<?php esc_html_e('Custom Label', 'woocommerce-canadapost-webservice'); ?>"><span class="dashicons dashicons-tag"></span></a>
								<a class="button canadapost-btn-icon canadapost-service-margin-edit" href="#" style="<?php echo !empty($custom_service_margin) ? 'display:none' : ''; ?>" title="<?php esc_html_e('Margin', 'woocommerce-canadapost-webservice'); ?>"><span class="canadapost-icon-letter">%</span></a>
								<span class="canadapost-service-label-wrapper" style="<?php echo empty($custom_service_label) ? 'display:none' : ''; ?>">
								<span class="canadapost-inline-label description"><?php esc_html_e('Label', 'woocommerce-canadapost-webservice'); ?>: &nbsp;</span>
								  <input name="woocommerce_cpwebservice_service_label_<?php echo esc_attr($this->get_service_code_field($service_code)) ?>" class="canadapost-input-lg" type="text" value="<?php echo esc_attr($custom_service_label); ?>" placeholder="<?php echo esc_attr($service_label); ?>" />
								    <a class="button canadapost-btn-icon canadapost-service-label-remove" href="#" title="<?php esc_html_e('Remove', 'woocommerce-canadapost-webservice'); ?>"><span class="dashicons dashicons-no-alt"></span></a>
								</span>
								<span class="canadapost-service-margin-wrapper" style="<?php echo empty($custom_service_margin) ? 'display:none' : ''; ?>">
								  <span class="canadapost-inline-label description"><?php esc_html_e('Margin', 'woocommerce-canadapost-webservice'); ?>: </span>
								  <input name="woocommerce_cpwebservice_service_margin_<?php echo esc_attr($this->get_service_code_field($service_code)) ?>" class="canadapost-input-lg" type="text" value="<?php echo esc_attr(!empty($custom_service_margin) ? $custom_service_margin : ''); ?>" placeholder="<?php esc_html_e('Add Cost/Margin $','woocommerce-canadapost-webservice')?> / %" />
								  <a class="button canadapost-btn-icon canadapost-service-margin-remove" href="#" title="<?php esc_html_e('Remove', 'woocommerce-canadapost-webservice'); ?>"><span class="dashicons dashicons-no-alt"></span></a>
								</span>
								<?php if (!empty($this->service_descriptions) && is_array($this->service_descriptions) && !empty($this->service_descriptions[$service_code])): ?>
								<small class="description canadapost-service-description"><?php echo esc_html($this->service_descriptions[$service_code]); ?></small>
								<?php endif; ?>
								</li>
							<?php endforeach; ?>
							</ul>
							</fieldset>
					</td>
				    </tr>
				    <?php if (!empty($this->packagetypes)): ?>
				    <tr>
					    <th scope="row" class="titledesc"><?php esc_html_e('Package Type', 'woocommerce-canadapost-webservice') ?></th>
						<td class="forminp">
								<select name="woocommerce_cpwebservice_packagetype" class="canadapost-packagetype">
								<?php foreach($this->packagetypes as $key=>$type): ?>
								<option value="<?php echo esc_attr($key); ?>" <?php selected($key == $this->options->packagetype); ?>><?php echo esc_html($type); ?></option>
								<?php endforeach;?>
								</select> 
								<p class="description"><?php esc_html_e('Packaging used with Parcel Services', 'woocommerce-canadapost-webservice') ?></p>
						</td>
					</tr>
					<?php endif; ?>
				    <tr>
				    <th scope="row" class="titledesc"><?php esc_html_e('Parcel Services Display', 'woocommerce-canadapost-webservice') ?></th>
					<td class="forminp">
                        <p><label for="woocommerce_cpwebservice_limit_rates">
                            <?php echo esc_html(sprintf(__('Limit the number of %s services displayed in the cart as shipping methods', 'woocommerce-canadapost-webservice'), $this->get_resource('method_title'))) ?>:
                            </label><br />
                            <select name="woocommerce_cpwebservice_limit_rates" id="cpwebservice_limit_rates">
                            <option value=""><?php esc_html_e('Display all available services', 'woocommerce-canadapost-webservice') ?></option>
                            <?php for($i=8;$i>1;$i--): ?>
                            <option value="<?php echo esc_attr($i) ?>" <?php selected($this->options->limit_rates == $i)?>><?php echo esc_html(sprintf(__('Display up to %s services', 'woocommerce-canadapost-webservice'), $i)) ?></option>
                            <?php endfor; ?>
                            <option value="1" <?php selected($this->options->limit_rates == 1)?>><?php esc_html_e('Display 1 service only', 'woocommerce-canadapost-webservice') ?></option>
                            </select>
                            <br /><span class="description bolder"><?php esc_html_e('The services will be displayed by lowest cost first.', 'woocommerce-canadapost-webservice') ?>
							</span><br />
							</p>
                            <!--<p><label><input name="woocommerce_cpwebservice_limit_prefer_delivery" type="checkbox" value="1" <?php checked(!empty($this->options->limit_prefer_delivery)); ?> /> 
                            <?php _e('If limiting the number of displayed services, prefer to include the lowest cost service that has Delivery dates or is a Guaranteed service.', 'woocommerce-canadapost-webservice') ?></label>
                            </p>
                            <p><label><input name="woocommerce_cpwebservice_limit_same_delivery" type="checkbox" value="1" <?php checked(!empty($this->options->limit_same_delivery)); ?> /> 
                            <?php esc_html_e('If services have the same Delivery date, only display the service with the lower cost.', 'woocommerce-canadapost-webservice') ?></label>
                            </p>-->
							<p><label for="woocommerce_cpwebservice_prefer_service">
							<input name="woocommerce_cpwebservice_prefer_service" id="woocommerce_cpwebservice_prefer_service" type="checkbox" value="1" <?php checked(!empty($this->options->prefer_service)); ?> /> <?php esc_html_e('If services are the same cost, only display the better service.', 'woocommerce-canadapost-webservice') ?></label>
							<br />
							&nbsp; &nbsp; <span class="description"><?php echo esc_html($this->get_resource('parcel_services')); ?></span>
						</p>
					</td>
				    </tr>
				    <tr>
				    <th scope="row" class="titledesc"><?php esc_html_e('Shipping Class Rules', 'woocommerce-canadapost-webservice')?> </th>
					<td class="forminp">
					
							<p><label for="woocommerce_cpwebservice_enable_rules">
								<input name="woocommerce_cpwebservice_enable_rules" id="woocommerce_cpwebservice_enable_rules" type="checkbox" value="1" <?php checked(!empty($this->options->rules_enable)); ?> /> <?php esc_html_e('Enable Shipping Class rules for Parcel Services', 'woocommerce-canadapost-webservice') ?></label> &nbsp; <span class="description"><?php esc_html_e('Assign products to these shipping classes to apply these rules.', 'woocommerce-canadapost-webservice')?></span></p>
					<div id="cpwebservice_rules">									
							<?php $this->shipping_class_rule(); ?>
					</div>
					</td>
				    </tr>
				    <tr><td colspan="2" style="padding-left:0;border-bottom: 1px solid #999;">
		                 <h3><?php esc_html_e('Boxes / Packing', 'woocommerce-canadapost-webservice') ?></h3>
				    </td></tr>
				    <tr>
					<th scope="row" class="titledesc"><?php esc_html_e('Box/Envelope Weight', 'woocommerce-canadapost-webservice') ?></th>
					<td class="forminp">
							<input type="text" name="woocommerce_cpwebservice_packageweight" id="woocommerce_cpwebservice_packageweight" style="max-width:50px;" value="<?php echo esc_attr($this->display_weight($this->options->packageweight)); ?>" /> <?php echo esc_html($this->options->display_weights)?> <span class="description"><?php echo esc_html(sprintf(__('Envelope/Box weight with bill/notes/advertising inserts (ex. 0.02%s)', 'woocommerce-canadapost-webservice'), $this->options->display_weights)) ?></span>
					</td>
				    </tr>
				    <tr>
				    <th scope="row" class="titledesc"><?php esc_html_e('Box Packing', 'woocommerce-canadapost-webservice') ?></th>
					<td class="forminp">
							
							<p><label for="woocommerce_cpwebservice_box_packing">
							<input type="radio" class="woocommerce_cpwebservice_packing_method" name="woocommerce_cpwebservice_packing_method" id="woocommerce_cpwebservice_box_packing" value="boxpack3d" <?php checked(empty($this->options->packing_method) || $this->options->packing_method == 'boxpack3d'); ?> />
							<?php esc_html_e('(Default)', 'woocommerce-canadapost-webservice') ?> <?php esc_html_e('Advanced 3D Box-Packing Algorithm used to pack products', 'woocommerce-canadapost-webservice') ?></label> &nbsp;
							<label for="woocommerce_cpwebservice_volumetric_packing">
							<input type="radio" class="woocommerce_cpwebservice_packing_method" name="woocommerce_cpwebservice_packing_method" id="woocommerce_cpwebservice_volumetric_packing" value="volumetric" <?php checked($this->options->packing_method,'volumetric'); ?> />
						    <?php esc_html_e('Volumetric/Cubic Packing Algorithm to pack products','woocommerce-canadapost-webservice')?></label>
						    <br />
							<span class="description"><?php esc_html_e('This determines the method used for packing products into packages. Advanced 3D packing works well for most situations.', 'woocommerce-canadapost-webservice') ?></span>
							</p>
							<p>								
							<label for="woocommerce_cpwebservice_volumetric_weight">
							<input name="woocommerce_cpwebservice_volumetric_weight" id="woocommerce_cpwebservice_volumetric_weight" type="checkbox" value="1" <?php checked(!empty($this->options->volumetric_weight)); ?> /> <?php esc_html_e('After packing, use Volumetric weight (if it is more than the actual weight) when requesting package rates', 'woocommerce-canadapost-webservice') ?> <?php echo esc_html($this->get_resource('volumetric_weight_recommend')) ?></label>
							<span class="woocommerce-help-tip"  data-tip="<?php echo esc_attr(sprintf(__( 'Most shipping couriers, including %s charge by volumetric weight (the size of the package) when it is greater than the actual weight.', 'woocommerce-canadapost-webservice' ), $this->get_resource('method_title'))) ?>"></span></p>
							
							<!-- <p style="display:none"><label for="woocommerce_cpwebservice_boxes_switch">
							<input name="woocommerce_cpwebservice_boxes_switch" id="woocommerce_cpwebservice_boxes_switch" type="checkbox" value="1" <?php checked(!empty($this->options->boxes_switch)); ?> /> <?php esc_html_e('If Boxes are enabled, this plugin will attempt to use defined boxes first when packing, but then allow packages up to the maximum size for the shipping service. If this is unchecked, it will only use defined boxes when packing.', 'woocommerce-canadapost-webservice') ?></label>
							</p> -->
							
							<p><label for="woocommerce_cpwebservice_weight_only_enabled">
							<input name="woocommerce_cpwebservice_weight_only_enabled" id="woocommerce_cpwebservice_weight_only_enabled" type="checkbox" value="1" <?php checked(!empty($this->options->weight_only_enabled)); ?> /> <?php esc_html_e('Allow products with weight-only (no dimensions) to still be calculated.', 'woocommerce-canadapost-webservice') ?></label>
							</p>
							<p>
							<label for="woocommerce_cpwebservice_product_shipping_options">
							<input name="woocommerce_cpwebservice_product_shipping_options" id="woocommerce_cpwebservice_product_shipping_options" type="checkbox" value="1" <?php checked(!empty($this->options->product_shipping_options)); ?> /> <?php esc_html_e('Enable the option to mark products as Package Separately (Pre-packaged)', 'woocommerce-canadapost-webservice') ?></label>
							<br />
							<span class="description"><?php esc_html_e('This option will be displayed on the Product Edit page in Woocommerce.', 'woocommerce-canadapost-webservice') ?></span>
							</p>
                            <br />
					</td>
				    </tr>
				    <tr valign="top">
					<th scope="row" class="titledesc"><?php esc_html_e('Shipping Package/Box sizes', 'woocommerce-canadapost-webservice') ?></th>
					<td class="forminp">
					<?php if (!isset($this->boxes) || !is_array($this->boxes)){
							$this->boxes = array(array('length'=>'10','width'=>'10', 'height'=>'6','name'=>'Standard Box'));
							$this->options->boxes_enable='';
						}
						$box_defaults = array('length'=>0,'width'=>0, 'height'=>0,'name'=>''); ?>
					<label for="woocommerce_cpwebservice_boxes_enable">
								<input name="woocommerce_cpwebservice_boxes_enable" id="woocommerce_cpwebservice_boxes_enable" type="checkbox" value="1" <?php checked($this->options->boxes_enable); ?> /> <?php esc_html_e('Enable Defined Shipping Box Sizes', 'woocommerce-canadapost-webservice') ?></label><br />
						<span class="description"><?php esc_html_e('Please define a number of envelope/package/box sizes that you use to ship. These will be used to ship but if a large enough box is not found for a product, the system will use a calculate one.', 'woocommerce-canadapost-webservice') ?></span>
						<br /><br />
						<div id="cpwebservice_boxes">							
							<?php for($i=0;$i<count($this->boxes); $i++): ?>
							<?php $box = (is_array($this->boxes[$i]) ? array_merge($box_defaults, $this->boxes[$i]) : array()); ?>
							<div class="cpwebservice_boxes_item form-field">
								<h4><?php esc_html_e('Box', 'woocommerce-canadapost-webservice'); ?> <div style="float:right;"><a href="javascript:;" title="<?php esc_html_e('Remove','woocommerce-canadapost-webservice'); ?>" class="button canadapost-btn-icon cpwebservice_box_remove"><span class="dashicons dashicons-no-alt"></span></a></div></h4>
								<div><span class="description"><?php esc_html_e('Box Name (internal)', 'woocommerce-canadapost-webservice'); ?>:</span><br />
								<input name="woocommerce_cpwebservice_box_name[]" class="input-text input-fullwidth" size="15" type="text" value="<?php echo esc_attr($box['name']); ?>"></div>
								<div><label for="woocommerce_cpwebservice_box_length[]"><?php esc_html_e('Box Dimensions', 'woocommerce-canadapost-webservice'); ?> (<?php echo esc_html($this->options->display_units)?>)</label></div>
								<div class="cpwebservice_box_dimensions">
									<input name="woocommerce_cpwebservice_box_length[]" placeholder="<?php esc_html_e('Length', 'woocommerce-canadapost-webservice'); ?>" class="input-text input-thirdwidth" size="6" type="text" value="<?php echo esc_attr($this->display_unit($box['length'])); ?>" />
									<input name="woocommerce_cpwebservice_box_width[]" placeholder="<?php esc_html_e('Width', 'woocommerce-canadapost-webservice'); ?>" class="input-text input-thirdwidth" size="6" type="text" value="<?php echo esc_attr($this->display_unit($box['width'])); ?>">
									<input name="woocommerce_cpwebservice_box_height[]" placeholder="<?php esc_html_e('Height', 'woocommerce-canadapost-webservice'); ?>" class="input-text last input-thirdwidth" size="6" type="text" value="<?php echo esc_attr($this->display_unit($box['height'])); ?>" />
									<div><span class="description"><?php echo esc_html(sprintf(__('LxWxH %s decimal form','woocommerce-canadapost-webservice'), '('.$this->options->display_units.')')); ?></span></div>
								</div>
								<div><label><?php esc_html_e('Box Weight','woocommerce-canadapost-webservice')?>:</label> <input name="woocommerce_cpwebservice_box_weight[]" class="input-text last input-thirdwidth" size="10" type="text" value="<?php echo esc_attr(isset($box['weight']) && $box['weight'] > 0 ? $this->display_weight($box['weight']) : ''); ?>" /> <span class="description"><?php echo esc_html($this->options->display_weights)?></span></div>
								<div><label><?php esc_html_e('Add Cost/Margin $','woocommerce-canadapost-webservice')?></label> <input name="woocommerce_cpwebservice_box_margin[]" class="input-text last input-thirdwidth" size="10" type="text" value="<?php echo esc_attr(isset($box['margin']) && $box['margin'] != 0 ? $box['margin'] : ''); ?>" /></div>
							</div>
							<?php endfor; ?>
						</div>
						<div style="clear:left;" class="clearfix"></div>
						<a href="javascript:;" id="btn_cpwebservice_boxes" class="button-secondary"><?php esc_html_e('Add More','woocommerce-canadapost-webservice'); ?> <span class="dashicons dashicons-plus-alt" style="margin-top:5px;"></span></a>
                        <div>
                        <br />
                          <div style="<?php echo empty($this->options->contractid) ? 'display:none' : '' ?>">
                            <label for="woocommerce_cpwebservice_max_box_default">
                            <input name="woocommerce_cpwebservice_max_box" id="woocommerce_cpwebservice_max_box_default" type="radio" value="0" <?php checked(empty($this->options->max_box) || $this->options->max_box===false); ?> /> <?php esc_html_e('Use Maximum dimensions for Packages as defined by ', 'woocommerce-canadapost-webservice') ?><?php echo esc_html($this->get_resource('method_title')) ?></label>
                            &nbsp;
                            <label for="woocommerce_cpwebservice_max_box_defined">
                            <input name="woocommerce_cpwebservice_max_box" id="woocommerce_cpwebservice_max_box_defined" type="radio" value="1" <?php checked($this->options->max_box===true); ?> /> <?php esc_html_e('Use Maximum dimensions for Packages of Largest defined Box.', 'woocommerce-canadapost-webservice') ?></label>
                            <p class="description"><?php esc_html_e('This is the maximum package dimensions that items will be packed in.', 'woocommerce-canadapost-webservice') ?></p>
                          </div>
                        </div>
                    </td>
				    </tr>
                    <tr>
                    <td colspan="2" style="padding-left:0;border-top: 1px solid #999;">
                    <h3><?php esc_html_e('Alternative (fallback/failover) weight and dimensions for Products', 'woocommerce-canadapost-webservice') ?></h3>
                        <label><input name="cpwebservice_altrates_defaults" type="checkbox" value="1" <?php checked($this->options->altrates_defaults==true); ?> />  <?php esc_html_e('Enable Default Weight or Dimensions for products missing data','woocommerce-canadapost-webservice'); ?>: </label>
                        <p class="description"><strong><?php esc_html_e(__('This will only be used if weight is not entered on Product data', 'woocommerce-canadapost-webservice')) ?>.</strong></p>
                    </td>
                    </tr>
                    <tr>
                    <th class="titledesc"><?php esc_html_e('Default product data when weight is missing', 'woocommerce-canadapost-webservice') ?></th>
                    <td>
                            <p class="form-field"><input name="cpwebservice_altrates_weight" style="max-width:70px" placeholder="<?php esc_html_e('Weight', 'woocommerce-canadapost-webservice') ?>" class="input-text" size="8" type="text" value="<?php echo esc_attr(!empty($this->options->altrates_weight) ? $this->display_weight($this->options->altrates_weight) : ''); ?>" /> <?php echo esc_html($this->options->display_weights)?></p>
                            <p class="form-field">
							<input name="cpwebservice_altrates_length" style="max-width:50px" placeholder="<?php esc_html_e('Length', 'woocommerce-canadapost-webservice') ?>" class="input-text" size="6" type="text" value="<?php echo esc_attr(!empty($this->options->altrates_length) ? $this->display_unit($this->options->altrates_length) : '' ); ?>" />
                            <input name="cpwebservice_altrates_width" style="max-width:50px" placeholder="<?php esc_html_e('Width', 'woocommerce-canadapost-webservice') ?>" class="input-text" size="6" type="text" value="<?php echo esc_attr(!empty($this->options->altrates_width) ? $this->display_unit($this->options->altrates_width) : ''); ?>">
                            <input name="cpwebservice_altrates_height" style="max-width:50px" placeholder="<?php esc_html_e('Height', 'woocommerce-canadapost-webservice') ?>" class="input-text last" size="6" type="text" value="<?php echo esc_attr(!empty($this->options->altrates_height) ? $this->display_unit($this->options->altrates_height) : ''); ?>" />
                            <span class="description"><?php echo esc_html(sprintf(/* translators: dimension units */__('(%s) LxWxH decimal form','woocommerce-canadapost-webservice'), $this->options->display_units)); ?> </span>
						    </p>
                    </td>
                    </tr>
				    </table>
				  </div><!-- /#cpwebservice_services -->
				  					  
		<div class="cpwebservice_panel cpwebservice_hidden" id="cpwebservice_tracking">
		<h3><?php esc_html_e('Tracking', 'woocommerce-canadapost-webservice') ?></h3>
		         <table class="form-table">
				    <tr valign="top">
					<th scope="row" class="titledesc"><?php esc_html_e('Order Shipping Tracking', 'woocommerce-canadapost-webservice') ?></th>
					<td class="forminp">
                        <p><?php esc_html_e('Parcel tracking updated on a schedule','woocommerce-canadapost-webservice') ?> (WP Cron): 
                            <br /> <span class="dashicons dashicons-clock" style="vertical-align:middle"></span> <?php esc_html_e('Daily approximately','woocommerce-canadapost-webservice') ?>:
                            <select name="cpwebservice_tracking_schedule_time" style="width:10em;vertical-align:middle">
                                <?php if (empty($this->options->tracking_schedule_time)){ $this->options->tracking_schedule_time = '6:00pm'; } ?>
                                <option value="9:00pm" <?php selected('9:00pm' == $this->options->tracking_schedule_time); ?>>9:00pm</option>
                                <option value="6:00pm" <?php selected('6:00pm' == $this->options->tracking_schedule_time); ?>>6:00pm<?php esc_html_e('(Default)', 'woocommerce-canadapost-webservice')?></option>
                                <option value="1:00pm" <?php selected('1:00pm' == $this->options->tracking_schedule_time); ?>>1:00pm</option>
                                <option value="9:00am" <?php selected('9:00am' == $this->options->tracking_schedule_time); ?>>9:00am</option>
							</select> 
                            <br />
                            </p>
						<p><label for="woocommerce_cpwebservice_email_tracking"><input name="woocommerce_cpwebservice_email_tracking" id="woocommerce_cpwebservice_email_tracking" type="checkbox" value="1" <?php checked($this->options->email_tracking==true); ?>  /> <?php esc_html_e('Enable Email notification when Parcel Tracking updates', 'woocommerce-canadapost-webservice') ?></label></p> 
						<p class="description"><?php esc_html_e('Automatic email notifications to customers when "Mailed on" or "Delivered" date is updated', 'woocommerce-canadapost-webservice')?></p>                        
                    </td>
				    </tr>
				    <tr valign="top">
					<th scope="row" class="titledesc"><?php esc_html_e('Tracking Email Format', 'woocommerce-canadapost-webservice') ?></th>
					<td class="forminp">
                    <p><?php esc_html_e('Email Notifications', 'woocommerce-canadapost-webservice') ?></p>
                    <p><a href="admin.php?page=wc-settings&tab=email&section=woocommerce_cpwebservice_trackingemail" target="_blank"><?php esc_html_e('Configure email notification for Shipped and Delivered updates', 'woocommerce-canadapost-webservice')?></a> <span class="dashicons dashicons-external" style="vertical-align:middle"></span></p>
					<h4><?php esc_html_e('Tracking Display', 'woocommerce-canadapost-webservice') ?></h4>   
                    <div id="cpwebservice_tracking_template_preview" style="display:none">
                       <img width="200" />
                    </div>
                    <p><label for="cpwebservice_tracking_template">
						<?php esc_html_e('Tracking Layout', 'woocommerce-canadapost-webservice') ?></label><br />
						<select name="cpwebservice_tracking_template" class="cpwebservice_tracking_template">
						    <option value="" data-template="<?php echo esc_attr(plugins_url( 'img/tracking-layout-1.png' , dirname(__FILE__) )) ?>"><?php esc_html_e('Tracking Details table layout', 'woocommerce-canadapost-webservice')?></option>
                            <option value="responsive" <?php selected('responsive' == $this->options->tracking_template); ?> data-template="<?php echo esc_attr(plugins_url( 'img/tracking-layout-2.png' , dirname(__FILE__) )) ?>"><?php esc_html_e('Tracking Responsive layout', 'woocommerce-canadapost-webservice') ?></option>
							<option value="minimal" <?php selected('minimal' == $this->options->tracking_template); ?> data-template="<?php echo esc_attr(plugins_url( 'img/tracking-layout-3.png' , dirname(__FILE__) )) ?>"><?php esc_html_e('Minimal: Only Tracking Number with Link', 'woocommerce-canadapost-webservice') ?></option>
                            <option value="progress" <?php selected('progress' == $this->options->tracking_template); ?> data-template="<?php echo esc_attr(plugins_url( 'img/tracking-layout-4.png' , dirname(__FILE__) )) ?>"><?php esc_html_e('Tracking Progress Responsive layout', 'woocommerce-canadapost-webservice') ?></option>
						</select></p>	
                        <p><label for="cpwebservice_tracking_icons"><input name="cpwebservice_tracking_icons" id="cpwebservice_tracking_icons" type="checkbox" value="1" <?php checked($this->options->tracking_icons==true); ?>  /> <?php esc_html_e('Display icons with Tracking information', 'woocommerce-canadapost-webservice') ?></label></p>
                        <p><label for="cpwebservice_tracking_heading">
						<?php esc_html_e('Heading for Tracking details', 'woocommerce-canadapost-webservice') ?></label><br />
                            <input type="text" class="input" name="cpwebservice_tracking_heading" value="<?php echo esc_attr($this->options->tracking_heading) ?>" placeholder="<?php esc_attr_e( 'Order Shipping Tracking', 'woocommerce-canadapost-webservice' ) ?>" /></p>
                        <p><label for="cpwebservice_tracking_action_email">
						<?php esc_html_e('Location of Tracking on Email', 'woocommerce-canadapost-webservice') ?></label><br />
						<select name="cpwebservice_tracking_action_email">
						    <option value=""><?php esc_html_e('After order table (Default)', 'woocommerce-canadapost-webservice')?></option>
							<option value="woocommerce_email_before_order_table" <?php selected('woocommerce_email_before_order_table' == $this->options->tracking_action_email); ?>><?php esc_html_e('Before order table', 'woocommerce-canadapost-webservice') ?></option>
						</select></p><br />
						<p><label for="cpwebservice_tracking_customer_action">
							<?php esc_html_e('Location of Tracking on Customer "My Account" Order Details page', 'woocommerce-canadapost-webservice') ?></label><br />
							<select name="cpwebservice_tracking_action_customer">
							    <option value=""><?php esc_html_e('After order table (Default)', 'woocommerce-canadapost-webservice')?></option>
								<option value="woocommerce_view_order" <?php selected('woocommerce_view_order' == $this->options->tracking_action_customer); ?>><?php esc_html_e('Before order details', 'woocommerce-canadapost-webservice') ?></option>
							</select>
							</p>
							<br />
                        <p><label><span class="description"><?php esc_html_e('Date format for display of Tracking Events', 'woocommerce-canadapost-webservice') ?></span></label></p>
                        <p><select name="cpwebservice_tracking_dateformat">
                                <?php if ($this->get_resource('tracking_dateonly')): ?>
                                    <option value="" <?php selected('', $this->options->tracking_dateformat); ?>><?php esc_html_e('Default (ex: 2028-02-29 )','woocommerce-canadapost-webservice') ?></option>
								    <option value="Y-m-d" <?php selected('M j, Y', $this->options->tracking_dateformat); ?>><?php esc_html_e('Date (ex: 2028-02-29)','woocommerce-canadapost-webservice')?></option>
                                    <option value="m/d/Y" <?php selected('m/d/Y', $this->options->tracking_dateformat); ?>><?php esc_html_e('Date (ex: 02/29/2028)','woocommerce-canadapost-webservice')?></option>
                                    <option value="M j, Y" <?php selected('M j, Y', $this->options->tracking_dateformat); ?>><?php esc_html_e('Date (ex: Feb 29, 2028)','woocommerce-canadapost-webservice')?></option>
                                    <option value="F j, Y" <?php selected('F j, Y', $this->options->tracking_dateformat); ?>><?php esc_html_e('Date (ex: February 29, 2028)','woocommerce-canadapost-webservice')?></option>
                                <?php else: ?>
								    <option value="" <?php selected('', $this->options->tracking_dateformat); ?>><?php esc_html_e('Default (ex: 2028-02-29 12:30:00 )','woocommerce-canadapost-webservice')?></option>
								    <option value="Y-m-d" <?php selected('M j, Y', $this->options->tracking_dateformat); ?>><?php esc_html_e('Date (ex: 2028-02-29)','woocommerce-canadapost-webservice')?></option>
                                    <option value="m/d/Y" <?php selected('m/d/Y', $this->options->tracking_dateformat); ?>><?php esc_html_e('Date (ex: 02/29/2028)','woocommerce-canadapost-webservice')?></option>
                                    <option value="m/d/Y g:i a" <?php selected('m/d/Y', $this->options->tracking_dateformat); ?>><?php esc_html_e('Date (ex: 02/29/2028 12:30 pm)','woocommerce-canadapost-webservice')?></option>
                                    <option value="M j, Y" <?php selected('M j, Y', $this->options->tracking_dateformat); ?>><?php esc_html_e('Date (ex: Feb 29, 2028)','woocommerce-canadapost-webservice')?></option>
                                    <option value="M j, Y g:i a" <?php selected('D M j, Y', $this->options->tracking_dateformat); ?>><?php esc_html_e('Date (ex: Feb 29, 2028 12:30 pm)','woocommerce-canadapost-webservice')?></option>
                                    <option value="F j, Y" <?php selected('F j, Y', $this->options->tracking_dateformat); ?>><?php esc_html_e('Date (ex: February 29, 2028)','woocommerce-canadapost-webservice')?></option>
                                    <option value="l M j, Y g:i a" <?php selected('l M j, Y g:i a', $this->options->tracking_dateformat); ?>><?php esc_html_e('Full Date (ex: Monday Feb 29, 2028 12:30 pm)','woocommerce-canadapost-webservice')?></option>
                                    <option value="F j, Y, g:i a" <?php selected('F j, Y, g:i a', $this->options->tracking_dateformat); ?>><?php esc_html_e('Full Date (ex: February 29, 2028 12:30 pm)','woocommerce-canadapost-webservice')?></option>
                                <?php endif; ?>
								</select> </p>
					</td>
				    </tr> 
				    
				  </table>
				  </div><!-- /#cpwebservice_tracking -->
		
				 <div class="cpwebservice_panel cpwebservice_hidden" id="cpwebservice_flatrates">
				  <table class="form-table">
				  <tr><th style="padding-left:0;border-bottom: 1px solid #999;">
				    <h3><?php esc_html_e('Lettermail / Flat Rates', 'woocommerce-canadapost-webservice') ?></h3>
				  </th>
				  </tr>
				     <tr valign="top">
					<td class="forminp">
					<?php if (empty($this->lettermail) || !is_array($this->lettermail) || count($this->lettermail) == 0){
							// Set default Lettermail Rates.
							/* Letter-post USA rates:(for now)
							0-100g = $2.20
							100g-200g = $3.80
							200g-500g = $7.60*/
							$this->lettermail = array(array('country'=>$this->get_resource('shipment_country'),'label'=>$this->get_resource('lettermail_default'), 'cost'=>'2.20','weight_from'=>'0','weight_to'=>'0.1', 'max_qty'=>0, 'min_total'=>'', 'max_total'=>''),
												    array('country'=>$this->get_resource('shipment_country'),'label'=>$this->get_resource('lettermail_default'), 'cost'=>'3.80','weight_from'=>'0.1','weight_to'=>'0.2', 'max_qty'=>0, 'min_total'=>'', 'max_total'=>''),
													array('country'=>$this->get_resource('shipment_country') == 'CA' ? 'US' : 'CA','label'=>$this->get_resource('lettermail_default'), 'cost'=>'3.80','weight_from'=>'0.1','weight_to'=>'0.2', 'max_qty'=>0, 'min_total'=>'', 'max_total'=>''),
													array('country'=>$this->get_resource('shipment_country') == 'CA' ? 'US' : 'CA','label'=>$this->get_resource('lettermail_default'), 'cost'=>'7.60','weight_from'=>'0.2','weight_to'=>'0.5', 'max_qty'=>0, 'min_total'=>'', 'max_total'=>''));
							$this->options->lettermail_enable=false;
						} 
						$lettermail_defaults = array('country'=>'', 'prov'=>'', 'label'=>'', 'cost'=>0,'weight_from'=>'','weight_to'=>'','max_qty'=>0, 'min_total'=>'', 'max_total'=>''); ?>
					<label><input name="cpwebservice_lettermail_enable" type="checkbox" value="1" <?php checked($this->options->lettermail_enable); ?> /> <?php esc_html_e('Enable Lettermail / Flat Rates', 'woocommerce-canadapost-webservice') ?> <?php esc_html_e('in the cart.', 'woocommerce-canadapost-webservice') ?></label>
                    <p class="description"><?php echo esc_html(sprintf(__('Define Lettermail rates based on Weight Range (%s)', 'woocommerce-canadapost-webservice'), $this->options->display_weights)) ?>.</p>
						<p class="description"> <?php echo esc_html(sprintf(__('Example: 0.1%s to 0.2%s: $3.80 Lettermail', 'woocommerce-canadapost-webservice'), $this->options->display_weights,  $this->options->display_weights)) ?></p>
                    <br />
                    <label><input name="cpwebservice_altrates_enable" type="checkbox" value="1" <?php checked($this->options->altrates_enable); ?> /> <?php echo esc_html(sprintf(__('Enable as alternative (fallback/failover) rates when Live %s rates are unavailable.', 'woocommerce-canadapost-webservice'), $this->get_resource('method_title'))) ?></label>
                    <p class="description"> <?php esc_html_e('Can enable rate rules to be used when live rates are not available', 'woocommerce-canadapost-webservice') ?></p>
                    <br />
						<?php
						// States/Prov. 
						$arr_prov =  WC()->countries->get_states( 'CA' );
                        $arr_states =  WC()->countries->get_states( 'US' );
            			 ?>
						<span id="cpwebservice_lettermail_statearray" data-states="<?php echo esc_attr(json_encode((array)$arr_states));  ?>" class="hidden"></span>
						<span id="cpwebservice_lettermail_provarray" data-provs="<?php echo esc_attr(json_encode((array)$arr_prov));  ?>" class="hidden"></span>
						<div id="cpwebservice_lettermail">							
							<?php for($i=0;$i<count($this->lettermail); $i++): ?>
							<?php $lettermail = (is_array($this->lettermail[$i]) ? array_merge($lettermail_defaults, $this->lettermail[$i]) : array()); ?>
							<div class="cpwebservice_lettermail_item">
                            <h4><?php esc_html_e('Rate Rule', 'woocommerce-canadapost-webservice'); ?> <div style="float:right;"><a href="javascript:;" title="<?php esc_attr_e('Remove','woocommerce-canadapost-webservice'); ?>" class="button canadapost-btn-icon cpwebservice_lettermail_remove"><span class="dashicons dashicons-no-alt"></span></a></div></h4>
                              <label for="cpwebservice_lettermail_label<?php echo esc_attr($i);?>"> <?php esc_html_e('Label', 'woocommerce-canadapost-webservice'); ?>:
									<input name="cpwebservice_lettermail_label[]" id="cpwebservice_lettermail_label<?php echo esc_attr($i);?>" style="max-width:250px" placeholder="<?php esc_html_e('Lettermail','woocommerce-canadapost-webservice'); ?>" class="input-text" size="30" type="text" value="<?php echo esc_attr($lettermail['label']); ?>" /></label>
                                    <label> <?php esc_html_e('Cost','woocommerce-canadapost-webservice'); ?>: $<input name="cpwebservice_lettermail_cost[]" id="cpwebservice_lettermail_cost<?php echo esc_attr($i);?>" style="max-width:80px" placeholder="<?php esc_attr_e('Cost','woocommerce-canadapost-webservice'); ?>" class="input-text" size="16" type="text" value="<?php echo esc_attr($lettermail['cost']); ?>"></span></label>
                                    <br />
                                    <label> <?php esc_html_e('Destination','woocommerce-canadapost-webservice'); ?>:</label>
                                    <select name="cpwebservice_lettermail_country[]" class="cpwebservice_lettermail_country">
									<option value="CA" <?php if ($lettermail['country']=='CA') echo 'selected="selected"'; ?>>Canada</option>
									<option value="US" <?php if ($lettermail['country']=='US') echo 'selected="selected"'; ?>>USA</option>
									<option value="INT" <?php if ($lettermail['country']=='INT') echo 'selected="selected"'; ?>><?php esc_html_e('International', 'woocommerce-canadapost-webservice') ?></option>
								</select>
        						 <select name="cpwebservice_lettermail_prov[]" class="cpwebservice_lettermail_prov">
        						    <option value="" <?php selected( '', esc_attr( $lettermail['country'] ) ); ?>></option>
            						<?php
            						if ($lettermail['country']!='INT'):      
            						  $lettermail_states = ($lettermail['country'] == 'CA') ? $arr_prov : $arr_states;
            						  foreach ( (array) $lettermail_states as $option_key => $option_value ) : ?>
            							<option value="<?php echo esc_attr( $option_key ); ?>" <?php selected( $option_key, esc_attr( $lettermail['prov'] ) ); ?>><?php echo esc_attr( $option_value ); ?></option>
            						<?php endforeach; ?>
            						<?php endif; ?>
            					</select>
								<br />
									<label> <?php esc_html_e('Weight Range','woocommerce-canadapost-webservice'); ?>(<?php echo esc_html($this->options->display_weights)?>): <input name="cpwebservice_lettermail_weight_from[]" id="cpwebservice_lettermail_weight_from<?php echo esc_attr($i);?>" style="max-width:70px" placeholder="" class="input-text" size="8" type="text" value="<?php echo esc_attr($this->display_weight($lettermail['weight_from'])); ?>" /><?php echo esc_html($this->options->display_weights)?>
									 <?php esc_html_e('to','woocommerce-canadapost-webservice'); ?> &lt;
									<input name="cpwebservice_lettermail_weight_to[]" id="cpwebservice_lettermail_weight_to<?php echo esc_attr($i);?>" style="max-width:80px" placeholder="" class="input-text last" size="8" type="text" value="<?php echo esc_attr($this->display_weight($lettermail['weight_to'])); ?>" /><?php echo esc_html($this->options->display_weights)?> </label>
									&nbsp; &nbsp; <label> <?php esc_html_e('Max items (0 for no limit)','woocommerce-canadapost-webservice'); ?>: <input name="cpwebservice_lettermail_max_qty[]" id="cpwebservice_lettermail_max_qty<?php echo esc_attr($i);?>" style="max-width:40px" placeholder="" class="input-text" size="8" type="text" value="<?php echo esc_attr($lettermail['max_qty']); ?>" /></label>
                                    <br />
                                    <label> <?php esc_html_e('Cart subtotal','woocommerce-canadapost-webservice'); ?>: $<input name="cpwebservice_lettermail_min_total[]" id="cpwebservice_lettermail_min_total<?php echo esc_attr($i);?>" style="max-width:80px" placeholder="" class="input-text" size="8" type="text" value="<?php echo esc_attr($lettermail['min_total']); ?>" />
									 <?php esc_html_e('to','woocommerce-canadapost-webservice'); ?> &lt;=
									$<input name="cpwebservice_lettermail_max_total[]" id="cpwebservice_lettermail_max_total<?php echo esc_attr($i);?>" style="max-width:80px" placeholder="" class="input-text last" size="8" type="text" value="<?php echo esc_attr($lettermail['max_total']); ?>" /></label>
                                    <br />
                                    <label><?php esc_html_e('Limit to Products in Shipping Classes','woocommerce-canadapost-webservice'); ?>: </label>
                                    <select name="cpwebservice_lettermail_class[<?php echo esc_attr($i) ?>][]" data-placeholder="<?php esc_html_e('Select Shipping Classes' , 'woocommerce-canadapost-webservice' )?>" class="chosen_select" multiple>
                                    <option value="0" <?php selected(isset($lettermail['shipping_class']) && is_array($lettermail['shipping_class']) && in_array(0, $lettermail['shipping_class']))?>><?php esc_html_e('No Shipping Class','woocommerce-canadapost-webservice'); ?></option>
                                    <?php foreach($this->get_all_shipping_classes() as $ship) { ?>
                                            <option value="<?php echo esc_attr($ship->term_id)?>" <?php selected(isset($lettermail['shipping_class']) && is_array($lettermail['shipping_class']) && in_array($ship->term_id, $lettermail['shipping_class']))?>><?php echo esc_html($ship->name)?></option>
                                    <?php }//end foreach ?>
                                    </select>
                                    <br />
                            </div>
							<?php endfor; ?>
                        </div>
                        <div style="clear:left;" class="clearfix"></div>
						<a href="javascript:;" id="btn_cpwebservice_lettermail" class="button-secondary"><?php esc_html_e('Add More','woocommerce-canadapost-webservice'); ?> <span class="dashicons dashicons-plus-alt" style="margin-top:5px;"></span></a>
                        <br />
						<br />
                        <p>
                        <label>
						<input name="cpwebservice_lettermail_exclude_tax" type="radio" value="0" <?php checked(empty($this->options->lettermail_exclude_tax)); ?> /> <?php esc_html_e('Cart subtotal of rules includes tax', 'woocommerce-canadapost-webservice') ?></label>
                        &nbsp; <label>
						<input name="cpwebservice_lettermail_exclude_tax" type="radio" value="1" <?php checked($this->options->lettermail_exclude_tax === true); ?> /> <?php esc_html_e('Cart subtotal of rules excludes tax', 'woocommerce-canadapost-webservice') ?></label>
                        </p>
                        <br />
						<label for="cpwebservice_lettermail_limits">
						<input name="cpwebservice_lettermail_limits" id="cpwebservice_lettermail_limits" type="checkbox" value="1" <?php checked($this->options->lettermail_limits); ?> /> <?php esc_html_e('Maximum dimensions for Lettermail/Flat Rates (Also maximum volumetric weight)', 'woocommerce-canadapost-webservice') ?></label>
						<p class="form-field">
							<input name="cpwebservice_lettermail_maxlength" id="cpwebservice_lettermail_maxlength" style="max-width:50px" placeholder="Length" class="input-text" size="6" type="text" value="<?php echo esc_attr(!empty($this->options->lettermail_maxlength) ? $this->display_unit($this->options->lettermail_maxlength) : '' ); ?>" />
									<input name="cpwebservice_lettermail_maxwidth" id="cpwebservice_lettermail_maxwidth" style="max-width:50px" placeholder="Width" class="input-text" size="6" type="text" value="<?php echo esc_attr(!empty($this->options->lettermail_maxwidth) ? $this->display_unit($this->options->lettermail_maxwidth) : ''); ?>">
									<input name="cpwebservice_lettermail_maxheight" id="cpwebservice_lettermail_maxheight" style="max-width:50px" placeholder="Height" class="input-text last" size="6" type="text" value="<?php echo esc_attr(!empty($this->options->lettermail_maxheight) ? $this->display_unit($this->options->lettermail_maxheight) : ''); ?>" />
									<span class="description"><?php echo esc_html(sprintf(__('(%s) LxWxH decimal form','woocommerce-canadapost-webservice'), $this->options->display_units)); ?> </span>
						</p>
						<br />
						<label for="cpwebservice_lettermail_override_weight">
								<input name="cpwebservice_lettermail_override_weight" id="cpwebservice_lettermail_override_weight" type="checkbox" value="1" <?php checked($this->options->lettermail_override_weight); ?> /> <?php esc_html_e('Override Box/Envelope Weights for Lettermail/Flat Rates', 'woocommerce-canadapost-webservice') ?></label>
						<p class="form-field">
							<input name="cpwebservice_lettermail_packageweight" id="cpwebservice_lettermail_packageweight" style="max-width:50px" class="input-text" size="6" type="text" value="<?php echo esc_attr(!empty($this->options->lettermail_packageweight) ? $this->display_weight($this->options->lettermail_packageweight) : ''); ?>" /><?php echo esc_html($this->options->display_weights)?> <span class="description"><?php esc_html_e('Envelope/Box weight. This is used instead of above Box/Envelope Weight, but only for calculating Lettermail/Flat Rates.', 'woocommerce-canadapost-webservice') ?></span></p>
					</td>
				    </tr>
			</table>
			</div><!-- /#cpwebservice_flatrates -->
			<?php if ($this->get_resource('shipments_implemented')===true): ?>
			<div class="cpwebservice_panel cpwebservice_hidden" id="cpwebservice_shipments">
		     <h3><?php esc_html_e('Shipments', 'woocommerce-canadapost-webservice') ?></h3>
		     
		       <table class="form-table">
		        <tr valign="top">
					<th scope="row" class="titledesc"><?php esc_html_e('Creating Shipments', 'woocommerce-canadapost-webservice') ?></th>
					<td class="forminp">
					<p><?php esc_html_e('The ability to Create Shipments (Paid shipping labels) is provided on the &quot;Edit Order&quot; page in Woocommerce. You will see a &quot;Create Shipment&quot; button.', 'woocommerce-canadapost-webservice')?>
					<br /><a href="<?php echo admin_url( 'edit.php?post_type=shop_order' ); ?>" target="_blank"><?php esc_html_e('View Orders', 'woocommerce-canadapost-webservice')?></a>
					</td>
					</tr>
				    <tr valign="top">
					<th scope="row" class="titledesc"><?php esc_html_e('Shipment Settings', 'woocommerce-canadapost-webservice') ?></th>
					<td class="forminp">
					<p><span class="dashicons dashicons-cart"></span> <?php esc_html_e('View my orders on', 'woocommerce-canadapost-webservice'); ?> <?php echo esc_html($this->get_resource('method_title'))?>
                    <br /><a href="<?php echo esc_attr($this->get_resource('method_website_orders_url')); ?>" target="_blank"><?php echo esc_html($this->get_resource('method_website_orders_url')); ?></a></p>
                    
					<p><?php echo esc_html(sprintf(__('In order to create paid shipping labels through this plugin, you will need to add a method of payment (Credit Card) on your account with %s.', 'woocommerce-canadapost-webservice'), $this->get_resource('method_title'))) ?></p>

<p><?php esc_html_e(sprintf(__('Log-in and add a default payment credit card to your %s online profile.', 'woocommerce-canadapost-webservice'), $this->get_resource('method_title'))) ?></p>
<?php if (!empty($this->options->contractid)):?>
<p><?php esc_html_e('To create a commercial shipping label you must be a commercial customer with a parcel agreement and have an account in good standing.', 'woocommerce-canadapost-webservice')?></p>
<p><?php esc_html_e('You may use “account” as an alternate method of payment. Please ensure that your account is in good standing.', 'woocommerce-canadapost-webservice') ?></p>
<?php endif; ?>


<p><?php esc_html_e('Add a method of Payment: (Visa/MasterCard/AmericanExpress)', 'woocommerce-canadapost-webservice') ?>
 <br /><a href="<?php echo esc_attr($this->get_resource('method_website_account_url'))?>"><?php echo esc_html($this->get_resource('method_website_account_url')); ?></a></p>
					</td>
				    </tr>
				    <tr valign="top">
				    <th scope="row" class="titledesc"><?php esc_html_e('Logging', 'woocommerce-canadapost-webservice') ?></th>
					<td class="forminp">
					<label for="woocommerce_cpwebservice_shipment_log_enable">
								<input name="woocommerce_cpwebservice_shipment_log_enable" id="woocommerce_cpwebservice_shipment_log_enable" type="checkbox" value="1" <?php checked($this->options->shipment_log); ?> /> <?php esc_html_e('Enable Shipment Logging', 'woocommerce-canadapost-webservice') ?>
								<br /><small><?php esc_html_e('Captures recent create shipment / label actions.  If there are any errors in creating shipments, they will be captured in this log.', 'woocommerce-canadapost-webservice') ?></small></label>
					<?php if ($this->options->shipment_log): ?>
					<div><a href="<?php echo wp_nonce_url( admin_url( 'admin-ajax.php?action=cpwebservice_shipment_log_display' ), 'cpwebservice_shipment_log_display' ); ?>" title="Display Log" class="button canadapost-shipment-log-display"><?php esc_html_e('Display log for recent shipments/labels','woocommerce-canadapost-webservice'); ?></a> <div class="canadapost-shipment-log-display-loading canadapost-spinner" style="display: none;"><div class="canadapost-spinner-b1"></div><div class="canadapost-spinner-b2"></div><div class="canadapost-spinner-b3"></div></div>
					<a href="#" class="button button-secondary canadapost-shipment-log-close" style="display:none"><span class="dashicons dashicons-no"></span></a>
					</div>
					<div id="cpwebservice_shipment_log_display" style="display:none;">
					<p></p>
					</div>
					<?php endif; ?> 
					</td>
				    </tr>
				    <tr valign="top">
				    <th scope="row" class="titledesc"><?php esc_html_e('Template', 'woocommerce-canadapost-webservice') ?></th>
					<td class="forminp">
					<p><?php esc_html_e('Common settings can be saved into a Template when creating a shipment label.', 'woocommerce-canadapost-webservice') ?><br />
					<?php esc_html_e('Please note that templates do not save the Customer Email, Phone and Order Reference Numbers of the shipment label.', 'woocommerce-canadapost-webservice') ?>
					</p>
					<label for="woocommerce_cpwebservice_shipment_template_package">
					<input name="woocommerce_cpwebservice_shipment_template_package" id="woocommerce_cpwebservice_shipment_template_package" type="checkbox" value="1" <?php checked($this->options->template_package); ?> /> <?php esc_html_e('Include package weight and dimensions in Shipment Templates', 'woocommerce-canadapost-webservice') ?>
					<br />
					<label for="woocommerce_cpwebservice_shipment_template_customs">
					<input name="woocommerce_cpwebservice_shipment_template_customs" id="woocommerce_cpwebservice_shipment_template_customs" type="checkbox" value="1" <?php checked($this->options->template_customs); ?> /> <?php esc_html_e('Include Customs Information (but not Customs Products) in Shipment Templates', 'woocommerce-canadapost-webservice') ?>
					<br />
					</td>
					</tr>
					<tr valign="top">
				    <th scope="row" class="titledesc"><?php esc_html_e('Customs', 'woocommerce-canadapost-webservice') ?></th>
					<td class="forminp">
					<p><?php esc_html_e('Customs product data can be saved with the product.', 'woocommerce-canadapost-webservice') ?>
					</p>
					<label for="woocommerce_cpwebservice_shipment_hscodes">
					<input name="woocommerce_cpwebservice_shipment_hscodes" id="woocommerce_cpwebservice_shipment_hscodes" type="checkbox" value="1" <?php checked($this->options->shipment_hscodes); ?> /> <?php esc_html_e('Enable saving Customs HS Codes and Country of Origin on Products', 'woocommerce-canadapost-webservice') ?>
					<br />
					</td>
					</tr>
				  </table>
			</div><!-- /#cpwebservice_shipments -->
			<?php endif; ?>
				<div class="cpwebservice_panel cpwebservice_hidden" id="cpwebservice_update">
		<h3><?php esc_html_e('Updates', 'woocommerce-canadapost-webservice') ?></h3>
		         <table class="form-table">
				    <tr valign="top">
					<th scope="row" class="titledesc"><?php esc_html_e('Updates Activation', 'woocommerce-canadapost-webservice') ?></th>
					<td class="forminp">
					<?php $display_updates = (!empty($this->upgrade) && is_object($this->upgrade) && !empty($this->upgrade->upgrade_token) && !empty($this->upgrade->licenseid)) ? true : false; ?>
					
					<div id="cpwebservice_update_form"<?php echo ($display_updates ? ' style="display:none"' : '') ?>>
			    	    <p class="description"><?php esc_html_e('Add your Envato license key to enable automatic updates in the Wordpress Admin. You can find the license key at the Code Canyon downloads area.', 'woocommerce-canadapost-webservice') ?></p>
						<p><?php esc_html_e('License Key / Purchase Code', 'woocommerce-canadapost-webservice')?>:</p>
						<p><input type="text" class="input" style="min-width:50%" name="woocommerce_cpwebservice_licenseid" id="woocommerce_cpwebservice_licenseid" value="" /></p>
						<p><?php esc_html_e('Email', 'woocommerce-canadapost-webservice')?>:</p>   
						<p><input type="text" class="input" style="min-width:100px" name="woocommerce_cpwebservice_license_email" id="woocommerce_cpwebservice_license_email" value="" /></p>
						<p><?php esc_html_e('Domain','woocommerce-canadapost-webservice')?>:</p>
						<p><?php echo esc_html(site_url('')) ?> <br />&nbsp;</p>
						<div><a href="<?php echo wp_nonce_url( admin_url( 'admin-ajax.php?action=cpwebservice_upgrades' ), 'cpwebservice_upgrades' ); ?>" id="btn_cpwebservice_license" class="button-secondary"><?php esc_html_e('Activate','woocommerce-canadapost-webservice'); ?> <span class="dashicons dashicons-update" style="margin-top:5px;"></span></a>
						 <div class="cpwebservice_ajax_licenseupdate canadapost-spinner" style="display: none;"><div class="canadapost-spinner-b1"></div><div class="canadapost-spinner-b2"></div><div class="canadapost-spinner-b3"></div></div>
						 <?php if ($display_updates) : ?><a href="javascript:;" id="btn_cpwebservice_license_refresh_cancel" class="button-secondary"><?php esc_html_e('Cancel','woocommerce-canadapost-webservice'); ?></a><?php endif; ?>
						 </div>
						<p>&nbsp;</p>
				    </div>
				    <div id="cpwebservice_update_error" style="display:none"><?php esc_html_e('License Key / Purchase code activation did not work. This may be because of an error or invalid license key. Check your purchase information and try again later.', 'woocommerce-canadapost-webservice')?></div>
					<div id="cpwebservice_update_display"<?php echo ($display_updates ? '' : ' style="display:none"') ?>>
						<?php 
						if ($display_updates){
						  cpwebservice_update::display_license( !empty($this->upgrade->licenseid) ? $this->upgrade->licenseid : '', !empty($this->upgrade->email) ? $this->upgrade->email : '', !empty($this->upgrade->upgrade_domain) ? $this->upgrade->upgrade_domain: ''); 
						}
						?>
					</div>
					
					</td>
				    </tr>
                    <?php if ($this->options->legacytracking || $this->options->legacyshipping): ?>
                    <tr>
                    <th><br />
                    <?php esc_html_e('Update Utilities', 'woocommerce-canadapost-webservice') ?>
                    </th>
                    </tr>
                    <?php if ($this->options->legacytracking): ?>
                    <tr>
                    <th>
                    <a href="<?php echo wp_nonce_url( admin_url( 'admin-ajax.php?action=cpwebservice_migrate_tracking_legacy_data' ), 'cpwebservice_migrate_tracking_legacy_data' ); ?>" id="cpwebservice_btn_tracking_migrate" class="button-secondary"><?php esc_html_e('Migrate Tracking Data','woocommerce-canadapost-webservice'); ?> <span class="dashicons dashicons-update" style="margin-top:5px;"></span></a>
                    <br /><div class="cpwebservice_ajax_tracking_migrate canadapost-spinner" style="display: none;"><div class="canadapost-spinner-b1"></div><div class="canadapost-spinner-b2"></div><div class="canadapost-spinner-b3"></div></div>
                    <div id="cpwebservice_result_tracking_migrate"></div>
                    </th>
                    <td></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($this->options->legacyshipping): ?>
                    <tr>
                    <th>
                    <a href="<?php echo wp_nonce_url( admin_url( 'admin-ajax.php?action=cpwebservice_migrate_shipping_legacy_data' ), 'cpwebservice_migrate_shipping_legacy_data' ); ?>" id="cpwebservice_btn_shipping_migrate" class="button-secondary"><?php esc_html_e('Migrate Shipping Data','woocommerce-canadapost-webservice'); ?> <span class="dashicons dashicons-update" style="margin-top:5px;"></span></a>
                    <br /><div class="cpwebservice_ajax_shipping_migrate canadapost-spinner" style="display: none;"><div class="canadapost-spinner-b1"></div><div class="canadapost-spinner-b2"></div><div class="canadapost-spinner-b3"></div></div>
                    <div id="cpwebservice_result_shipping_migrate"></div>
                    </th>
                    <td></td>
                    </tr>
                    <?php endif; ?>
                    <?php endif; ?>
				  </table>
				  </div><!-- /#cpwebservice_update -->			
		<?php 
		}

		function process_admin_options() {

			
			// check for security
			if (!isset($_POST['cpwebservice_options_noncename']) || !wp_verify_nonce($_POST['cpwebservice_options_noncename'], plugin_basename(__FILE__))) 
				return;


			if(isset($_POST['woocommerce_cpwebservice_enabled'])) $this->options->enabled = 'yes'; else $this->options->enabled ='no';
			if(isset($_POST['woocommerce_cpwebservice_title'])) $this->options->title = wc_clean($_POST['woocommerce_cpwebservice_title']);
			if(isset($_POST['woocommerce_cpwebservice_account'])) $this->options->account = wc_clean($_POST['woocommerce_cpwebservice_account']);
			if(isset($_POST['woocommerce_cpwebservice_contractid'])) $this->options->contractid = wc_clean($_POST['woocommerce_cpwebservice_contractid']);
			if(isset($_POST['woocommerce_cpwebservice_api_user'])) $this->options->api_user = wc_clean($_POST['woocommerce_cpwebservice_api_user']);
			if(isset($_POST['woocommerce_cpwebservice_api_key'])) $this->options->api_key = wc_clean($_POST['woocommerce_cpwebservice_api_key']);
			if ($this->get_resource('account_onlyuserid')===true && !empty($this->options->api_user) && empty($this->options->api_key) && empty($this->options->account)){ // means password is generated;
			    $this->options->api_key = hash('sha256', $this->options->api_user);  $this->options->account = $this->options->api_user;
			}
			if(isset($_POST['woocommerce_cpwebservice_mode'])) $this->options->mode = wc_clean($_POST['woocommerce_cpwebservice_mode']);
			if(isset($_POST['woocommerce_cpwebservice_delivery'])) $this->options->delivery = floatval(wc_clean($_POST['woocommerce_cpwebservice_delivery'])); 
			if ($this->options->delivery==0) { $this->options->delivery = ''; }
			if(isset($_POST['woocommerce_cpwebservice_delivery_guarantee'])) $this->options->delivery_guarantee = true; else $this->options->delivery_guarantee = false;
			if(isset($_POST['woocommerce_cpwebservice_delivery_format'])) $this->options->delivery_format = wc_clean($_POST['woocommerce_cpwebservice_delivery_format']);
			if(isset($_POST['woocommerce_cpwebservice_delivery_label'])) $this->options->delivery_label = wc_clean($_POST['woocommerce_cpwebservice_delivery_label']);
			if(isset($_POST['woocommerce_cpwebservice_margin_value']))  $this->options->margin_value = floatval(wc_clean($_POST['woocommerce_cpwebservice_margin_value']));
			if(isset($_POST['woocommerce_cpwebservice_margin'])) $this->options->margin = floatval(wc_clean($_POST['woocommerce_cpwebservice_margin']));
			if (!empty($this->options->margin) && $this->options->margin == 0) { $this->options->margin = ''; } // percentage only != 0
			if(isset($_POST['woocommerce_cpwebservice_exchange_rate'])) $this->options->exchange_rate = floatval(wc_clean($_POST['woocommerce_cpwebservice_exchange_rate']));
			if (!empty($this->options->exchange_rate) && $this->options->exchange_rate == 0) { $this->options->exchange_rate = ''; } // percentage only != 0
			if(isset($_POST['woocommerce_cpwebservice_packageweight'])) $this->options->packageweight = $this->save_weight(floatval($_POST['woocommerce_cpwebservice_packageweight']));
			if(isset($_POST['woocommerce_cpwebservice_log_enable'])) $this->options->log_enable = true; else $this->options->log_enable = false;
			if(isset($_POST['woocommerce_cpwebservice_boxes_enable'])) $this->options->boxes_enable = true; else $this->options->boxes_enable = false;
            if(isset($_POST['cpwebservice_lettermail_enable'])) $this->options->lettermail_enable = true; else $this->options->lettermail_enable = false;
            if(isset($_POST['cpwebservice_altrates_enable'])) $this->options->altrates_enable = true; else $this->options->altrates_enable = false;
			if(isset($_POST['woocommerce_cpwebservice_shipping_tracking'])) $this->options->shipping_tracking = true; else $this->options->shipping_tracking = false;
			if(isset($_POST['woocommerce_cpwebservice_email_tracking'])) $this->options->email_tracking = true; else $this->options->email_tracking = false;
			if(isset($_POST['cpwebservice_tracking_icons'])) $this->options->tracking_icons = true; else $this->options->tracking_icons = false;
			if(isset($_POST['cpwebservice_tracking_action_customer'])) $this->options->tracking_action_customer= wc_clean($_POST['cpwebservice_tracking_action_customer']);
			if(isset($_POST['cpwebservice_tracking_action_email'])) $this->options->tracking_action_email = wc_clean($_POST['cpwebservice_tracking_action_email']);
            if(isset($_POST['cpwebservice_tracking_template'])) $this->options->tracking_template = wc_clean($_POST['cpwebservice_tracking_template']);
            if(isset($_POST['cpwebservice_tracking_heading'])) $this->options->tracking_heading = wc_clean($_POST['cpwebservice_tracking_heading']);
            if(isset($_POST['cpwebservice_tracking_dateformat'])) $this->options->tracking_dateformat = wc_clean($_POST['cpwebservice_tracking_dateformat']);
			if(isset($_POST['woocommerce_cpwebservice_display_required_notice'])) $this->options->display_required_notice = true; else $this->options->display_required_notice = false;
			update_option('cpwebservice_require_postal', $this->options->display_required_notice  ? 'yes' : 'no');
			if(isset($_POST['woocommerce_cpwebservice_enable_rules'])) $this->options->rules_enable = true; else $this->options->rules_enable = false;
			if(isset($_POST['woocommerce_cpwebservice_product_shipping_options'])) $this->options->product_shipping_options = true; else $this->options->product_shipping_options = false;
			if(isset($_POST['woocommerce_cpwebservice_volumetric_weight'])) $this->options->volumetric_weight = true; else $this->options->volumetric_weight = false;
			if(isset($_POST['woocommerce_cpwebservice_weight_only_enabled'])) $this->options->weight_only_enabled = true; else $this->options->weight_only_enabled = false;
            if(isset($_POST['woocommerce_cpwebservice_limit_same_delivery'])) $this->options->limit_same_delivery = true; else $this->options->limit_same_delivery = false;
            if(isset($_POST['woocommerce_cpwebservice_limit_prefer_delivery'])) $this->options->limit_prefer_delivery = true; else $this->options->limit_prefer_delivery = false;
            if(isset($_POST['woocommerce_cpwebservice_prefer_service'])) $this->options->prefer_service = true; else $this->options->prefer_service = false;
            if(isset($_POST['woocommerce_cpwebservice_limit_rates'])) $this->options->limit_rates = intval(wc_clean($_POST['woocommerce_cpwebservice_limit_rates']));
            if (!empty($this->options->limit_rates) && $this->options->limit_rates < 1 || $this->options->limit_rates > 8) { $this->options->limit_rates = null; }
            if(isset($_POST['woocommerce_cpwebservice_packagetype'])) $this->options->packagetype = wc_clean($_POST['woocommerce_cpwebservice_packagetype']);
			if(isset($_POST['woocommerce_cpwebservice_geolocate_origin'])) $this->options->geolocate_origin = true; else $this->options->geolocate_origin = false;
			if(isset($_POST['woocommerce_cpwebservice_geolocate_limit'])) $this->options->geolocate_limit = true; else $this->options->geolocate_limit = false;
			if(isset($_POST['woocommerce_cpwebservice_availability'])) $this->options->availability = wc_clean($_POST['woocommerce_cpwebservice_availability']);
			if(isset($_POST['woocommerce_cpwebservice_availability_countries']) && is_array($_POST['woocommerce_cpwebservice_availability_countries'])){
			    $this->options->availability_countries = wc_clean(implode(',', $_POST['woocommerce_cpwebservice_availability_countries']));
			} else { $this->options->availability_countries = ''; };
            if(isset($_POST['woocommerce_cpwebservice_packing_method'])) $this->options->packing_method = wc_clean($_POST['woocommerce_cpwebservice_packing_method']);
            if(isset($_POST['woocommerce_cpwebservice_max_box']) && $_POST['woocommerce_cpwebservice_max_box'] == '1') $this->options->max_box = true; else $this->options->max_box = false;
			
            // Tracking scheduled time
            $prev_tracking_schedule_time = isset($this->options->tracking_schedule_time) ? $this->options->tracking_schedule_time : null;
            if(isset($_POST['cpwebservice_tracking_schedule_time'])) $this->options->tracking_schedule_time = wc_clean($_POST['cpwebservice_tracking_schedule_time']);
            // Set schedule hook if different than saved.
            if ((!empty($this->options->tracking_schedule_time) && isset($prev_tracking_schedule_time) && $this->options->tracking_schedule_time != $prev_tracking_schedule_time)
                || empty($prev_tracking_schedule_time)){
                    do_action('cpwebservice_schedule_hook_action', $this->options->tracking_schedule_time);
            }

			// Source postal code (rates)
			if(isset($_POST['woocommerce_cpwebservice_source_postalcode'])) $this->options->source_postalcode = wc_clean($_POST['woocommerce_cpwebservice_source_postalcode']);
			$this->options->source_postalcode = str_replace(' ','',strtoupper($this->options->source_postalcode)); // N0N0N0 format only
			
			// services
			if(isset($_POST['woocommerce_cpwebservice_services']) && is_array($_POST['woocommerce_cpwebservice_services'])) {
				// save valid options. ( returns an array containing all the values of array1 that are present in array2 - in this case, an array of valid service codes)
				$this->services = array_intersect($_POST['woocommerce_cpwebservice_services'], array_keys($this->available_services));
				update_option('woocommerce_cpwebservice_services', $this->services);
			}
			// service labels
			$this->service_labels = array();
			$this->options->service_margin = array();
			foreach($this->available_services as $service_code=>$service_label) {
			    $service_code_field = $this->get_service_code_field($service_code);
			    if(!empty($_POST['woocommerce_cpwebservice_service_label_'.$service_code_field]) && $_POST['woocommerce_cpwebservice_service_label_'.$service_code_field] != $service_label) {
			        // save valid labels
			        $this->service_labels[$service_code] = wc_clean($_POST['woocommerce_cpwebservice_service_label_'.$service_code_field]);
				}
				if(!empty($_POST['woocommerce_cpwebservice_service_margin_'.$service_code_field]) && $_POST['woocommerce_cpwebservice_service_margin_'.$service_code_field] != '0') {
					// save valid margins
					$margin_val = wc_clean($_POST['woocommerce_cpwebservice_service_margin_'.$service_code_field]);
					$margin_percent = false; // decimal amount or percent (ie, 4.90 or -2.1%)
					if(!empty($margin_val) && substr($margin_val, -1) == '%'){
						$margin_percent = true;
						$margin_val = str_replace('%','', $margin_val);
					 }
					if(!empty($margin_val) && floatval($margin_val) != 0){
						$this->options->service_margin[$service_code] = floatval($margin_val) . ($margin_percent ? '%' : '');
					}
			    }
			}
			update_option('woocommerce_cpwebservice_service_labels', $this->service_labels);
			
			
			// boxes
			if( isset($_POST) && isset($_POST['woocommerce_cpwebservice_box_length']) && is_array($_POST['woocommerce_cpwebservice_box_length']) ) {
				$boxes = array();
			
				for ($i=0; $i < count($_POST['woocommerce_cpwebservice_box_length']); $i++){
					$box = array();
					$box['length'] = isset($_POST['woocommerce_cpwebservice_box_length'][$i]) ? $this->save_unit(floatval($_POST['woocommerce_cpwebservice_box_length'][$i])) : '';
					$box['width'] = isset($_POST['woocommerce_cpwebservice_box_width'][$i]) ? $this->save_unit(floatval($_POST['woocommerce_cpwebservice_box_width'][$i])) : '';
					$box['height'] = isset($_POST['woocommerce_cpwebservice_box_height'][$i]) ? $this->save_unit(floatval($_POST['woocommerce_cpwebservice_box_height'][$i])) : '';
					$box['name'] = isset($_POST['woocommerce_cpwebservice_box_name'][$i]) ? wc_clean($_POST['woocommerce_cpwebservice_box_name'][$i]) : '';
					$box['weight'] = isset($_POST['woocommerce_cpwebservice_box_weight'][$i]) ? $this->save_weight(floatval($_POST['woocommerce_cpwebservice_box_weight'][$i])) : '';
					$box['margin'] = isset($_POST['woocommerce_cpwebservice_box_margin'][$i]) ? number_format(floatval($_POST['woocommerce_cpwebservice_box_margin'][$i]),1,'.','') : '';
					if (empty($box['weight'])) { $box['weight'] = ''; }
					if (empty($box['margin'])) { $box['margin'] = ''; }
					// Cubed/volumetric
					$box['cubic'] = $box['length'] * $box['width'] * $box['height'];
					
					$boxes[] = $box;
				}
			
				$this->boxes = $boxes;
				update_option('woocommerce_cpwebservice_boxes', $this->boxes);
			}
			
			// rules
			if( isset($_POST) && isset($_POST['cpwebservice_rule_classes']) && is_array($_POST['cpwebservice_rule_classes']) ) {
				$rules = array();
				for ($i=0; $i < count($_POST['cpwebservice_rule_classes']); $i++){
					$rule = array();
					if (isset($_POST['cpwebservice_rule_classes'][$i]) && isset($_POST['cpwebservice_rule_services'][$i])){
						$rule['shipping_class'] =  wc_clean($_POST['cpwebservice_rule_classes'][$i]);
						$rule['services'] = array();
						foreach($_POST['cpwebservice_rule_services'][$i] as $svc){
							$rule['services'][] = wc_clean($svc);
						}
					}
					if (!empty($rule['shipping_class'])){
					   $rules[] = $rule;
					}
				}
			
				$this->rules = $rules;
				update_option('woocommerce_cpwebservice_rules', $this->rules);
			}
			
			// lettermail
			if( isset($_POST) && isset($_POST['cpwebservice_lettermail_country']) && is_array($_POST['cpwebservice_lettermail_country']) ) {
				$lettermail = array();
			
				for ($i=0; $i < count($_POST['cpwebservice_lettermail_country']); $i++){
					$row = array();
					$row['country'] = isset($_POST['cpwebservice_lettermail_country'][$i]) ? wc_clean($_POST['cpwebservice_lettermail_country'][$i]) : '';
					$row['prov'] = isset($_POST['cpwebservice_lettermail_prov'][$i]) ? wc_clean($_POST['cpwebservice_lettermail_prov'][$i]) : '';
					$row['label'] = isset($_POST['cpwebservice_lettermail_label'][$i]) ? wc_clean($_POST['cpwebservice_lettermail_label'][$i]) : '';
					$row['cost'] = isset($_POST['cpwebservice_lettermail_cost'][$i]) ? number_format(floatval($_POST['cpwebservice_lettermail_cost'][$i]),2,'.','') : '';
					$row['weight_from'] = isset($_POST['cpwebservice_lettermail_weight_from'][$i]) ? $this->save_weight(floatval($_POST['cpwebservice_lettermail_weight_from'][$i])) : '';
					$row['weight_to'] = isset($_POST['cpwebservice_lettermail_weight_to'][$i]) ? $this->save_weight(floatval($_POST['cpwebservice_lettermail_weight_to'][$i])) : '';
					if ($row['weight_from'] > $row['weight_to']) { $row['weight_from'] = $row['weight_to']; } // Weight From must be a lesser value.
					$row['max_qty'] = isset($_POST['cpwebservice_lettermail_max_qty'][$i]) ? intval($_POST['cpwebservice_lettermail_max_qty'][$i]) : 0;
					$row['min_total'] = !empty($_POST['cpwebservice_lettermail_min_total'][$i]) ? number_format(floatval($_POST['cpwebservice_lettermail_min_total'][$i]),2,'.','') : '';
					$row['max_total'] = !empty($_POST['cpwebservice_lettermail_max_total'][$i]) ? number_format(floatval($_POST['cpwebservice_lettermail_max_total'][$i]),2,'.','') : '';
                    if( isset($_POST['cpwebservice_lettermail_class']) && is_array($_POST['cpwebservice_lettermail_class']) && isset($_POST['cpwebservice_lettermail_class'][$i])) {
                        $row['shipping_class'] = array();
                        foreach($_POST['cpwebservice_lettermail_class'][$i] as $classid){
                            $row['shipping_class'][] = intval($classid);
                        }
                    }
                    
					$lettermail[] = $row;
				}
			
				$this->lettermail = $lettermail;
				update_option('woocommerce_cpwebservice_lettermail', $this->lettermail);
            }
            if(isset($_POST['cpwebservice_lettermail_exclude_tax']) && $_POST['cpwebservice_lettermail_exclude_tax'] =='1') $this->options->lettermail_exclude_tax = true; else $this->options->lettermail_exclude_tax = false;
			if(isset($_POST['cpwebservice_lettermail_limits'])) $this->options->lettermail_limits = true; else $this->options->lettermail_limits = false;
			if(isset($_POST['cpwebservice_lettermail_maxlength'])) $this->options->lettermail_maxlength = $this->save_unit(floatval($_POST['cpwebservice_lettermail_maxlength']));
			if(isset($_POST['cpwebservice_lettermail_maxwidth'])) $this->options->lettermail_maxwidth = $this->save_unit(floatval($_POST['cpwebservice_lettermail_maxwidth']));
			if(isset($_POST['cpwebservice_lettermail_maxheight'])) $this->options->lettermail_maxheight = $this->save_unit(floatval($_POST['cpwebservice_lettermail_maxheight']));
			if (empty($this->options->lettermail_maxlength)) $this->options->lettermail_maxlength = '';
			if (empty($this->options->lettermail_maxwidth)) $this->options->lettermail_maxwidth = '';
			if (empty($this->options->lettermail_maxheight)) $this->options->lettermail_maxheight = '';
			if(isset($_POST['cpwebservice_lettermail_packageweight'])) $this->options->lettermail_packageweight = $this->save_weight(floatval($_POST['cpwebservice_lettermail_packageweight']));
            if(isset($_POST['cpwebservice_lettermail_override_weight'])) $this->options->lettermail_override_weight = true; else $this->options->lettermail_override_weight = false;
            // Alternative product
            if(isset($_POST['cpwebservice_altrates_defaults'])) $this->options->altrates_defaults = true; else $this->options->altrates_defaults = false;
            if(isset($_POST['cpwebservice_altrates_weight'])) $this->options->altrates_weight = $this->save_weight(floatval($_POST['cpwebservice_altrates_weight']));
			if(isset($_POST['cpwebservice_altrates_length'])) $this->options->altrates_length = $this->save_unit(floatval($_POST['cpwebservice_altrates_length']));
            if(isset($_POST['cpwebservice_altrates_width'])) $this->options->altrates_width = $this->save_unit(floatval($_POST['cpwebservice_altrates_width']));
            if(isset($_POST['cpwebservice_altrates_height'])) $this->options->altrates_height = $this->save_unit(floatval($_POST['cpwebservice_altrates_height']));
            if (empty($this->options->altrates_weight)) $this->options->altrates_weight = '';
            if (empty($this->options->altrates_length)) $this->options->altrates_length = '';
            if (empty($this->options->altrates_width)) $this->options->altrates_width = '';
            if (empty($this->options->altrates_height)) $this->options->altrates_height = '';
            
			//Shipments
			//
			if( isset($_POST['woocommerce_cpwebservice_shipment_postalcode']) && is_array($_POST['woocommerce_cpwebservice_shipment_postalcode']) ) {
			    $address = array();
			    $default_postalcode = '';
			    $geo = new cpwebservice_location();
			    	
			    for ($i=0; $i < count($_POST['woocommerce_cpwebservice_shipment_postalcode']); $i++){
			        $row = array('default'=>false,'contact'=>'','phone'=>'','postalcode'=>'','address'=>'','address2'=>'','city'=>'','prov'=>'','country'=>'','origin'=>true, 'postalcode_lat'=>0,'postalcode_lng'=>0);
			        $row['contact'] = isset($_POST['woocommerce_cpwebservice_shipment_contact'][$i]) ? wc_clean($_POST['woocommerce_cpwebservice_shipment_contact'][$i]) : '';
			        $row['phone'] = isset($_POST['woocommerce_cpwebservice_shipment_phone'][$i]) ? wc_clean($_POST['woocommerce_cpwebservice_shipment_phone'][$i]) : '';
			        $row['address'] = isset($_POST['woocommerce_cpwebservice_shipment_address'][$i]) ? wc_clean($_POST['woocommerce_cpwebservice_shipment_address'][$i]) : '';
			        $row['address2'] = isset($_POST['woocommerce_cpwebservice_shipment_address2'][$i]) ? wc_clean($_POST['woocommerce_cpwebservice_shipment_address2'][$i]) : '';
			        $row['city'] = isset($_POST['woocommerce_cpwebservice_shipment_city'][$i]) ? wc_clean($_POST['woocommerce_cpwebservice_shipment_city'][$i]) : '';
			        $row['prov'] = isset($_POST['woocommerce_cpwebservice_shipment_prov'][$i]) ? wc_clean($_POST['woocommerce_cpwebservice_shipment_prov'][$i]) : '';
			        $row['country'] = isset($_POST['woocommerce_cpwebservice_shipment_country'][$i]) ? wc_clean($_POST['woocommerce_cpwebservice_shipment_country'][$i]) : '';
			        $row['postalcode'] = isset($_POST['woocommerce_cpwebservice_shipment_postalcode'][$i]) ? wc_clean($_POST['woocommerce_cpwebservice_shipment_postalcode'][$i]) : '';
			        $row['postalcode'] = str_replace(' ','',strtoupper($row['postalcode'])); // N0N0N0 format only
			        $row['origin'] = (isset($_POST['woocommerce_cpwebservice_shipment_postalcode_origin']) && is_array($_POST['woocommerce_cpwebservice_shipment_postalcode_origin']) && in_array($i, $_POST['woocommerce_cpwebservice_shipment_postalcode_origin'])) ? true : false;
			        // Default Address radio group
			        $row['default'] = (isset($_POST['woocommerce_cpwebservice_shipment_default']) && intval($_POST['woocommerce_cpwebservice_shipment_default'])==$i) ? true : false;
			        if ($row['default']){
			            $default_postalcode = $row['postalcode'];
			        }
			        // Find Geo lat/long if postal code is valid.
			        if (!empty($row['postalcode'])){
			            $prefix = $geo->postal_prefix($row['postalcode']);
			            
			            if ($row['country'] == 'CA'){ // Canada Postal
			                $latlng = $geo->lookup_postal_location($prefix);
			            } elseif(in_array($row['country'], array('US','PR','VI','GU','UM','MP'))){ // US Zip
			                $latlng = $geo->lookup_zip_location($prefix);
			                // Backup plan if not found: Use US State.
			                if ($latlng == null && !empty($row['prov'])){
			                    $latlng = $geo->lookup_state_location($row['prov']);
			                }
			            } else {
			                $latlng = null; // international
			            }
			            
			            if (!empty($latlng)){
    			            $row['postalcode_lat'] = $latlng[0];
    			            $row['postalcode_lng'] = $latlng[1];
			            }
			        }
			        	
			        $address[] = $row;
			    }
			    $this->shipment_address = $address;
			    update_option('woocommerce_cpwebservice_shipment_address', $this->shipment_address);
			    
			    // Set default source_postalcode. (set if empty)
			    if (empty($default_postalcode) && !empty($address)){
			        $default_postalcode = $address[0]['postalcode'];
			    }
			    // Set source postalcode from the default address's postalcode.
			    $this->options->source_postalcode = $default_postalcode;
			}
			
			if(isset($_POST['woocommerce_cpwebservice_shipment_mode'])) $this->options->shipment_mode = wc_clean($_POST['woocommerce_cpwebservice_shipment_mode']);
			if(isset($_POST['woocommerce_cpwebservice_api_dev_user'])) $this->options->api_dev_user = wc_clean($_POST['woocommerce_cpwebservice_api_dev_user']);
			if(isset($_POST['woocommerce_cpwebservice_api_dev_key'])) $this->options->api_dev_key = wc_clean($_POST['woocommerce_cpwebservice_api_dev_key']);
			if(isset($_POST['woocommerce_cpwebservice_shipments_enabled'])) $this->options->shipments_enabled = true; else $this->options->shipments_enabled = false;
			if(isset($_POST['woocommerce_cpwebservice_shipment_log_enable'])) $this->options->shipment_log = true; else $this->options->shipment_log = false;
			if(isset($_POST['woocommerce_cpwebservice_shipment_template_package'])) $this->options->template_package = true; else $this->options->template_package = false;
			if(isset($_POST['woocommerce_cpwebservice_shipment_template_customs'])) $this->options->template_customs = true; else $this->options->template_customs = false;
			if(isset($_POST['woocommerce_cpwebservice_shipment_hscodes'])) $this->options->shipment_hscodes = true; else $this->options->shipment_hscodes = false;
			
			// shipment implementation
			if ($this->get_resource('shipments_implemented')===false) { $this->options->shipments_enabled = false; }
			
			// update options.
			update_option('woocommerce_cpwebservice', $this->options);
		}
		
		
		/**
		 * Return admin options as a html string.
		 * @return string
		 */
		public function get_admin_options_html() {
		    if ( $this->instance_id ) {
		        $settings_html= 'Configure options.' .  $this->instance_id;  
		    } else {
		        $settings_html= 'Global options.';   
		    }
		    return '<table class="form-table">' . $settings_html . '</table>';
		}
		
		/**
		 * Ajax function to Display Rates Lookup Log.
		 */
		public function rates_log_display() {
		
			// Let the backend only access the page
			if( !is_admin() ) {
				wp_die( esc_attr__( 'You do not have sufficient permissions to access this page.' ) );
			}
				
			// Check the user privileges
			if( !current_user_can( 'manage_woocommerce_orders' ) && !current_user_can( 'edit_shop_orders' ) ) {
				wp_die( esc_attr__( 'You do not have sufficient permissions to access this page.' ) );
			}
				
			// Nonce.
			if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'cpwebservice_rates_log_display' ) )
				wp_die( esc_attr__( 'You do not have sufficient permissions to access this page.' ) );
			
			
			if (false !== ( $log = get_transient('cpwebservice_log') ) && !empty($log)){
			$log = (object) array_merge(array('cart'=>array(),'params'=>array(),'request'=>array(),'rates'=>array(), 'datestamp'=>''), (array) $log);
				?>
									<h4><?php esc_html_e('Cart Shipping Rates Request', 'woocommerce-canadapost-webservice')?> - <?php echo date("F j, Y, g:i a",$log->datestamp); ?></h4>
									<table class="table widefat">
									<tr><th><?php esc_html_e('Item', 'woocommerce-canadapost-webservice')?></th><th style="width:10%"><?php esc_html_e('Qty', 'woocommerce-canadapost-webservice')?></th><th><?php esc_html_e('Weight', 'woocommerce-canadapost-webservice')?></th><th><?php esc_html_e('Dimensions', 'woocommerce-canadapost-webservice')?></th><th><?php esc_html_e('Cubic', 'woocommerce-canadapost-webservice')?></th></tr>
									<?php foreach($log->cart as $cart):?>
									<tr>
									<td><?php echo edit_post_link(esc_html($cart['item']),'','',$cart['id'])?></td><td><?php echo esc_html($cart['quantity'])?></td><td><?php echo esc_html($this->display_weight($cart['weight']))?><?php echo esc_html($this->options->display_weights)?> <?php echo (!empty($cart['altdefault']) && $cart['altdefault']) ? esc_html__('(Default)', 'woocommerce-canadapost-webservice'):'' ?></td><td><?php echo esc_html($cart['quantity'])?>* (<?php echo esc_html($this->display_unit($cart['length']))?><?php echo esc_html($this->options->display_units) ?> x <?php echo esc_html($this->display_unit($cart['width']))?><?php echo esc_html($this->options->display_units) ?> x <?php echo esc_html($this->display_unit($cart['height']))?><?php echo esc_html($this->options->display_units) ?>)</td><td><?php echo esc_html($this->display_unit_cubed($cart['cubic']))?><?php echo esc_html($this->options->display_units) ?><sup>3</sup></td>
									</tr>
									<?php endforeach; ?>
									</table>
									
									<h4><?php esc_html_e('Request to API', 'woocommerce-canadapost-webservice')?></h4>
									<p class="description"><?php esc_html_e('After box packing/Volumetric weight calculation and Box/Envelope Weight', 'woocommerce-canadapost-webservice')?></p>
									<table class="table widefat">
									<tr><th><?php esc_html_e('Origin Postal', 'woocommerce-canadapost-webservice')?></th><th><?php esc_html_e('Packages', 'woocommerce-canadapost-webservice')?> (<?php echo count($log->params)?>)</th><th><?php esc_html_e('Country', 'woocommerce-canadapost-webservice')?>, <?php esc_html_e('State', 'woocommerce-canadapost-webservice')?></th><th><?php esc_html_e('Destination', 'woocommerce-canadapost-webservice')?></th><th><?php esc_html_e('Shipping Weight', 'woocommerce-canadapost-webservice')?></th><th><?php esc_html_e('Dimensions', 'woocommerce-canadapost-webservice')?></th><th><?php esc_html_e('# of Products', 'woocommerce-canadapost-webservice') ?></th><th><?php esc_html_e('Class', 'woocommerce-canadapost-webservice')?></th></tr>
									<?php foreach($log->params as $i=>$params) { ?>
									<tr>
									<td><?php echo esc_html($params['source_postalcode'])?></td><td><?php echo (($i+1) . (isset($params['box_name']) ? esc_html(': '.$params['box_name']) : '') . (!empty($params['box_margin']) ? esc_html(' ('.__('Margin','woocommerce-canadapost-webservice').': $'.$params['box_margin']).')' : '')) ?></td><td><?php echo esc_html($params['country'])?>, <?php echo esc_html($params['state'])?></td><td><?php echo esc_html($params['postal'])?></td><td><strong><?php echo esc_html($this->display_weight($params['shipping_weight']))?></strong><?php echo esc_html($this->options->display_weights)?> <br />(<?php esc_html_e('Actual', 'woocommerce-canadapost-webservice')?> <?php echo esc_html($this->display_weight($params['package_weight']))?><?php echo esc_html($this->options->display_weights)?>)
									 <?php echo (!empty($params['box_weight']) ? '<br />'.esc_html(' ('.__('Box Weight', 'woocommerce-canadapost-webservice').' '.$this->display_weight($params['box_weight']).$this->options->display_weights.')') : '') ?>
									</td>
									<td><?php echo esc_html($this->display_unit($params['length']))?><?php echo esc_html($this->options->display_units) ?> x <?php echo esc_html($this->display_unit($params['width']))?><?php echo esc_html($this->options->display_units) ?> x <?php echo esc_html($this->display_unit($params['height']))?><?php echo esc_html($this->options->display_units) ?></td><td><?php echo !empty($params['product_count']) ? esc_html($params['product_count']) : '' ?></td><td><?php if (!empty($params['shipping_class'])){ $class_names = get_terms(array('product_shipping_class'), array('fields' => 'id=>name','include',$params['shipping_class']));  if (!empty($class_names) && count($class_names)>0) { echo esc_html(implode(',', array_values($class_names)));  } }?></td>
									<?php //array('country'=>$country, 'state'=>$state, 'postal'=>$postal, 'shipping_weight'=>$shipping_weight, 'length'=>$length, 'width'=>$width, 'height'=>$height); ?>
									</tr>
									<?php } //endforeach ?>
									</table>
									<h4><?php esc_html_e('API Response', 'woocommerce-canadapost-webservice')?></h4>
									<table class="table widefat">
									<tr><td>
									<?php foreach($log->request as $request):?>
									<?php if (!empty($request)): ?>
									<div class="cpwebservice_log_col"><?php echo str_replace("\n","<br />",str_replace("\n\n",'</div><div class="cpwebservice_log_col">',esc_html($request))); ?></div>
									<?php endif;?>
									<?php endforeach; ?>
									</td>
									</tr></table>
									<?php if (!empty($log->info)):?>
									<ul>
									<?php foreach($log->info as $info):?>
									<li>&bull; <?php echo esc_html($info) ?></li>
									<?php endforeach;?>
									</ul>
									<?php endif; ?>
									<h4><?php esc_html_e('Rates displayed in Cart', 'woocommerce-canadapost-webservice')?></h4>
									<?php if(!empty($log->rates)): ?>
									<table class="table widefat">
									<?php foreach($log->rates as $rates):?>
									<tr>
									<th><?php echo esc_html($rates->label) ?></th>
									<td><?php echo esc_html(number_format((float)$rates->cost, 2)) ?>
									</td>
									</tr>
									<?php endforeach; ?>
									</table>
									<?php else: ?>
									<p><?php esc_html_e('No rates displayed', 'woocommerce-canadapost-webservice') ?></p>
									<?php endif; ?>
									<?php } else { ?>
					<?php esc_html_e('No log information.. yet.  Go to your shopping cart page and click on "Calculate Shipping".', 'woocommerce-canadapost-webservice') ?>
					<?php  } // endif
			
		exit;
        }
        
        public function get_all_shipping_classes() {
            if (empty($this->shipping_classes)){
                $this->shipping_classes = get_terms(array('product_shipping_class'), array('hide_empty' => 0));
            }
            return $this->shipping_classes;
        }
		
		public function shipping_class_rule(){
			if (empty($this->rules) || !is_array($this->rules)){
				$this->rules = array();
			}
			// ensure there are at least 3 rules available (can change to any number as it's unlimited)
			$display_rules_num = 3;
			if (count($this->rules) < $display_rules_num){
				for($i=count($this->rules);$i<$display_rules_num;$i++){
					$this->rules[] = array('shipping_class'=>'', 'services'=>array(''));
				}
			}
			$shipping_class = $this->get_all_shipping_classes();
			?>
			<p class="description"><?php esc_html_e('Make sure that you have a common/overlapping service in all the rules so that if a customer places products from different shipping classes, there will be a valid service provided.', 'woocommerce-canadapost-webservice') ?>
			<table class="form-table" style="width:auto" id="cpwebservice_class_rules">
			<?php foreach($this->rules as $i => $rule){ ?>
			<tr class="cpwebservice_rules">
			<td><label>&nbsp;</label>
			<select name="cpwebservice_rule_classes[<?php echo esc_attr($i) ?>]" data-placeholder="<?php esc_html_e('Choose a Shipping Class...' , 'woocommerce-canadapost-webservice' )?>" class="chosen_select">
        	<option value=""></option>
        <?php foreach($shipping_class as $ship) { ?>
				<option value="<?php echo esc_attr($ship->term_id)?>" <?php selected(isset($rule['shipping_class']) && $rule['shipping_class']==$ship->term_id)?>><?php echo esc_html($ship->name)?></option>
		<?php 	}//end foreach ?>
        </select>
        </td><td>
        	<?php $current_group = ''; ?>
			<label><?php esc_html_e('Can only use', 'woocommerce-canadapost-webservice')?>:</label>
			 <select name="cpwebservice_rule_services[<?php echo esc_attr($i) ?>][]" data-placeholder="<?php esc_html_e(sprintf(__('Choose %s Services...' , 'woocommerce-canadapost-webservice' ), $this->get_resource('method_title')))?>" class="widefat chosen_select" multiple>
	            <option value=""></option>
	            <?php foreach($this->available_services as $service_code => $label) { ?>
	            <?php 
	            	$group = $this->get_destination_from_service($service_code);
	            	if ($current_group != $group) { ?>
	            	<?php if ($current_group != '') { echo '</optgroup>'; } // endif; ?>
	            	<?php $current_group = $group; ?>
	            	<optgroup label="<?php echo esc_attr($group)?>">
	            	<?php } // endif ?>
	              		<option value="<?php echo esc_attr($service_code)?>" <?php selected(isset($rule['services']) && is_array($rule['services']) && in_array($service_code,$rule['services']))?>><?php echo esc_html($label)?></option>
	            <?php } // endforeach ?>
	            <?php if ($current_group != '') { ?>
	            	</optgroup>
	            	<?php } // endif; ?>
	            	<option value="CP.NONE" <?php selected(isset($rule['services']) && is_array($rule['services']) && in_array('CP.NONE',$rule['services']))?>><?php esc_html_e(sprintf(__('No %s Shipping', 'woocommerce-canadapost-webservice'), $this->get_resource('method_title'))) ?></option>
                    <option value="CP.NOLM" <?php selected(isset($rule['services']) && is_array($rule['services']) && in_array('CP.NOLM',$rule['services']))?>><?php esc_html_e('No Lettermail/Flat Rates', 'woocommerce-canadapost-webservice') ?></option>
	          </select>
	          </td><td>
	          <label>&nbsp;</label><br />
	          <a href="javascript:;" class="button-secondary btn_cpwebservice_rules_clear"><?php esc_html_e('Clear','woocommerce-canadapost-webservice'); ?></a>
	          </td> 
	          </tr>
			<?php
			}// end foreach  ?>
			</table>
			 <a href="javascript:;" id="btn_cpwebservice_add_rule" class="button-secondary"><?php esc_html_e('Add More','woocommerce-canadapost-webservice'); ?> <span class="dashicons dashicons-plus-alt" style="margin-top:5px;"></span></a>
			<?php 
		}
    			
    			
		/*
		 * Function that does the lookup with the api.
		 */
		abstract public function call_validate_api_credentials($customerid,$contractid,$api_user,$api_key,$source_postalcode,$mode);
		
		
		/**
		 * Load and generate the template output with ajax
		 */
		public function validate_api_credentials() {
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
				
			if(empty( $_POST['api_user'] ) &&  $this->get_resource('account_onlyuserid')===true) {
			    wp_die( esc_attr__( 'API username, API password, Customer ID and Sender Address (Origin Postal Code) are required.' , 'woocommerce-canadapost-webservice' ) );
			}
			
			if( $this->get_resource('account_onlyuserid')!==true && (empty( $_POST['api_user'] )  || empty( $_POST['api_key'] ) || empty( $_POST['customerid'] ) || empty($_POST['source_postalcode']) )) {
    			wp_die( esc_attr__( 'API username, API password, Customer ID and Sender Address (Origin Postal Code) are required.' , 'woocommerce-canadapost-webservice' ) );
    		}
			
		
			// Nonce.
			if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'cpwebservice_validate_api_credentials' ) )
				wp_die( esc_attr__( 'You do not have sufficient permissions to access this page.' ) );
				
			// Get api_user, api_key, customerid
			$api_user = sanitize_text_field( $_POST['api_user'] );
			$api_key = sanitize_text_field( $_POST['api_key'] );
			$customerid = sanitize_text_field( $_POST['customerid'] );
			$contractid = sanitize_text_field( $_POST['contractid'] );
			$source_postalcode = sanitize_text_field( $_POST['source_postalcode'] );
			$source_postalcode = str_replace(' ','',strtoupper($source_postalcode)); //N0N0N0 (no spaces, uppercase)
			$mode = isset($_GET['mode']) && ($_GET['mode']=='live' || $_GET['mode'] == 'dev') ? sanitize_text_field( $_GET['mode'] ) : 'dev';
			
			// Do lookup with service. This method outputs the info.
			$this->call_validate_api_credentials($customerid,$contractid,$api_user,$api_key,$source_postalcode, $mode);
			

			exit;
    }
    			
    /*
     * Required function: GetRates.
     */
    abstract public function get_rates($dest_country, $dest_state, $dest_city, $dest_postal_code, $weight_kg, $length, $width, $height, $services = array(), $add_options = null,  $price_details = null);
	
	/*
	 * Main Lookup Rates function
	 */
	function calculate_shipping( $package = array() ) {	
	    // Need to calculate total package weight.
	    // Get total volumetric weight.
	    $total_quantity = 0;
	    $total_weight = 0;
	    $max = array('length'=>0, 'width'=>0, 'height'=>0);
	    $dimension_unit = get_option( 'woocommerce_dimension_unit' );
        $weight_unit = get_option( 'woocommerce_weight_unit' );
        $item_defaults = $this->get_product_altrates_default();
        $invalid_products = false;
	    $item_weight = 0;
	    $shipping_weight = 0;
	    $length = $width = $height = 0;
        $cubic = 0;
        $product_id = null;
        $parent_id = null;
	    $products = array();
	
	    foreach ( $package['contents'] as $item_id => $values ) {
	        if ( $values['quantity'] > 0 && $values['data']->needs_shipping()) {
	            $total_quantity += $values['quantity'];
	            $item_weight = cpwebservice_resources::convert($values['data']->get_weight(), $weight_unit, 'cm');
	            $length = 0; $width = 0; $height = 0;
	            if ( $values['data']->has_dimensions() ) {
	                $dimensions = array($values['data']->get_length(), $values['data']->get_width(), $values['data']->get_height());
	                if (count($dimensions) >= 3) {
	                    $length = cpwebservice_resources::convert($dimensions[0], $dimension_unit, 'cm');
	                    $width = cpwebservice_resources::convert( $dimensions[1], $dimension_unit, 'cm');
	                    $height = cpwebservice_resources::convert( $dimensions[2], $dimension_unit, 'cm');
	                }
                }
                // Alternate (fallback/failsafe) weight
                if ($item_weight == 0 && !empty($item_defaults['weight']) && $item_defaults['weight'] > 0){
                    $item_weight = $item_defaults['weight'];
                    if ($length == 0 && $width==0 && $height == 0){
                        $length = $item_defaults['length'];
                        $width = $item_defaults['width'];
                        $height = $item_defaults['height'];
                    }
                }
                $total_weight +=  $item_weight * $values['quantity'];

                 // Allow products with weight-only (no dimensions) to still be calculated.
	            if ($length == 0 && $width==0 && $height == 0 && $item_weight > 0 && $this->options->weight_only_enabled) {
	                // Weight-only so use reverse-volumetric weight.
	                // volumetric weight = cubic / 6000 : 0; //Canada Post: (L cm x W cm x H cm)/6000
	                $volumetric_cubic = $item_weight * 6000;
	                // cube Root volume instead of Boxes. (item is assumed already packaged to ship)
	                $dimension = (float)pow($volumetric_cubic, 1.0/3.0);
	                // A cube is the best estimate we can make.
	                $length = $width = $height = cpwebservice_resources::round_decimal($dimension,3);
                }
	
	            // Max dimensions
	            if ($length > $max['length']) {  $max['length'] = $length; }
	            if ($width > $max['width']) {  $max['width'] = $width; }
	            if ($height > $max['height']) {  $max['height'] = $height; }
                // Product data
                $product_id = method_exists($values['data'], 'get_id') ? $values['data']->get_id() : ($values['data']->is_type('variation') ? $values['data']->get_variation_id() : $values['data']->id);
                $parent_id = method_exists($values['data'], 'get_parent_id') ?  ($values['data']->is_type('variation') ? $values['data']->get_parent_id() : '') : ($values['data']->is_type('variation') ? $values['data']->id : '');
                
	            // Add to Products Array
	            if ($item_weight > 0 && $length > 0 && $width > 0 && $height > 0){
	                
	               $shipping_class = $values['data']->get_shipping_class_id();
	                // Lookup custom product options
	                $product_shipping_prepacked = get_post_meta( $product_id, '_cpwebservice_product_shipping', true );
	                for($j=0;$j<intval($values['quantity']);$j++){
	                    $products[] = array('length'=>$length, 'width'=>$width, 'height'=>$height, 'item_id'=> $product_id,  'weight'=> $item_weight, 'cubic'=>($length * $width * $height), 'prepacked'=>$product_shipping_prepacked, 'parent_id'=>$parent_id,'class'=>$shipping_class);
	                }
	            }
	            	
	            // Total Cubic size (for Lettermail calculation)
	            $cubic +=  $length * $width * $height * $values['quantity'];
	
	            // Cart Logging
	            if ($this->options->log_enable){
	                $this->log->cart[] = array('id'=> $values['data']->is_type('variation') ?  $parent_id : $product_id, 
                        'item'=> $values['data']->get_title() . ( $values['data']->is_type('variation') && method_exists($values['data'], 'get_variation_attributes') ? ' - '.implode(',', array_values($values['data']->get_variation_attributes())) : ''),
                        'quantity'=>$values['quantity'], 'weight'=>$item_weight * $values['quantity'], 'altdefault' => ($item_weight > 0 && !$values['data']->has_weight()),
	                    'length'=>$length, 'width'=>$width, 'height'=>$height, 'cubic'=>($length * $width * $height * $values['quantity']));
                }
                // Invalid if item has 0 weight
                if ($item_weight == 0){
                    // Item needs_shipping but has 0 weight. Not able to calculate a rate accurately.
                    if (apply_filters('cpwebservice_calculate_require_weight', false)){
                        // Do not calculate if this filter returns true.
                        $invalid_products = true;
                    }
                    if($this->options->log_enable){
                        $this->log->info[] = ($invalid_products ? 'Not able to calculate a rate ' : 'Calculation warning') . " - Product has 0 ".esc_html($this->options->display_weights)." weight: ".$values['data']->get_title()." (id #".($values['data']->is_type('variation') ?  $parent_id : $product_id).
                            ").  Consider adding weight to product (or enabling alternative/fallback weight setting).";
                    }
                }
            }
	    }
        
        if (!$invalid_products) {
            // Max box size (container in cm and kg) as defined on Shipping Method documentation.
            // Girth: length + (height x 2) + (width x 2)
            // No one dimension may exceed 2 m (200 cm)
            // Max. length + girth = 3 m (300 cm)
            $max_cp_box = $this->get_resource('max_cp_box');  // ex: array('length'=>200 , 'width'=> 200, 'height'=>200, 'girth'=> 300, 'weight'=> 30);
            
            // Max number rate lookups (To avoid webservice usage issues)
            $max_cp_lookups = 10;
            
            // Packages
            $containers = array();
            
            // Envelope weight with bill/notes/advertising inserts: ex. 20g
            $max_cp_box['weight'] -= (!empty($this->options->packageweight) ? floatval($this->options->packageweight) : 0);
            
            // Pack Service.
            $packservice = new cpwebservice_packservice(array('boxes_switch'=>$this->options->boxes_switch, 'pack_with_boxes' => ($this->options->boxes_enable && is_array($this->boxes)), 'max_cp_box'=>$max_cp_box, 'largest_as_max_box' => $this->options->max_box));
            
            // Add Pre-packed products
            $products_tmp = array();
            foreach($products as $i => $p ){
                // If Pre-Packed is enabled.
                if (!empty($this->options->product_shipping_options)){
                    if (isset($p['prepacked']) && $p['prepacked']=='yes') {
                        $containers[] = $packservice->to_container($p);
                    } else {
                        $products_tmp[] = $p;
                    }
                }
                
                if ($p['weight'] > $max_cp_box['weight']){
                    // Cart has a product that is too heavy to pack or even mail on its own. Cancel lookups.
                    $products = array();
                    break;
                }
            }
            
            // Exclude products that are pre-packed
            if (count($containers) > 0){
                $products = $products_tmp;
            }
            
            // Sort products for consistant packing.
            usort($products, array(&$this, 'sort_products'));
            
            // Pack Products.
            $containers = $packservice->productpack($products, $this->boxes, $containers, $this->options->packing_method == 'volumetric' ? 'volumetric' : 'boxpack3d');
        
            // Destination information (Need this to calculate rate)
            $country = $package['destination']['country']; // 2 char country code
            $state = $package['destination']['state']; // 2 char prov/state code
            $postal = $package['destination']['postcode']; // postalcode/zip code as entered by user.
            $postal = $this->postal_code_required($country, $postal); // allow countries without postal codes
            $city = !empty($package['destination']['city']) ? $package['destination']['city'] : null; // city as entered in checkout. (used in international rate calculation).
        
            // Get a rate to ship the package.
            if ($country != '' && is_array($containers) && !empty($postal)) {
                
                // Determine origin postal code (if needed)
                if ($this->options->geolocate_origin && in_array($country, array('CA','US','PR','VI','GU','UM','MP')) ){
                    // Get array of origin postal codes.
                    $origin = array();
                    foreach($this->shipment_address as $a){
                        if (isset($a['origin']) && $a['origin'] && !empty($a['postalcode']) && isset($a['postalcode_lat']) && isset($a['postalcode_lng'])){
                            $origin[$a['postalcode']] = array($a['postalcode_lat'], $a['postalcode_lng']);
                        }
                    }
                    // Determine if Products are restricted to certain warehouses. (Only available if more than one source address)
                    if($this->options->geolocate_limit && count($origin) > 1){
                        $limit_warehouse = $this->get_product_warehouses($containers);
                        // Modify $origin array if needed; Split shipment if $origin would be empty from a conflict.
                        if (!empty($limit_warehouse)){
                            // Set the limited_origin (If 0 returned, it will use the default sender address.
                            $origin = $this->limit_product_warehouse_origin($origin, $limit_warehouse, $this->shipment_address);
                            if (!empty($origin) && count($origin) == 1){
                                // If only 1 origin address, set the source_postalcode
                                $origin_postal = array_keys($origin);
                                $this->options->source_postalcode = $origin_postal[0]; 
                                if ($this->options->log_enable){
                                    $this->log->info[] = "Geolocation limited to " . $origin_postal[0];
                                }
                            }
                            // else no combination of warehouses could be found.
                            // Have to use default origin for shipping rate. If you want to split the shipments for each warehouse that can ship the product,
                            // this calculation would need to be done before the packing is done so as to group same-origin products together in packing and in rate lookup.
                        }
                    }
                    // Require more than 1 to make it worth it. (Otherwise, it'll just be the default, which is saved to options->source_postalcode
                    if (!empty($origin) && count($origin) > 1){
                        // Look up Lat/Lng for destination postal.
                        $distance = 0;
                        $min_distance = 999999;
                        $source_postalcode = $this->options->source_postalcode;
                        $geo = new cpwebservice_location();
                        foreach($origin as $origin_postal=>$loc){
                            // if it happens..
                            if ($geo->postal_prefix($origin_postal) == $geo->postal_prefix($postal)){
                                $source_postalcode = $origin_postal;
                                break;
                            }
                            // Calculate distance 
                            $distance = $geo->distance($loc[0], $loc[1], $country, $state, $postal);
                            // Distance null means the calculation failed.
                            // Finding the origin with the least amount of distance to the destination.
                            if ($distance != null && $distance < $min_distance){
                                $min_distance = $distance;
                                $source_postalcode = $origin_postal;
                            }
                        }
                        // Min-distance $source_postalcode
                        $this->options->source_postalcode = $source_postalcode;
                        if ($this->options->log_enable){
                            $this->log->info[] = "Warehouse with least amount of distance to the destination: " . $source_postalcode;
                        }
                    }
                }
                
                // Loop $containers to get_rates.
                $rates = array();
                $rates_combined = array();
                $package_weight = 0;
                $cp_lookups = 0;
                $api_limit = apply_filters('cpwebservice_api_limit', 0); // Throttle Limit: ie. 120/min
                // Ensure service codes are in _all_ rate objects
                $distinct_service_codes = array();
                foreach($containers as $i=>$shipping_package) {
                    $package_weight = ($shipping_package['weight'] > 0) ? $shipping_package['weight'] : 0;
                    if ($this->options->volumetric_weight){
                        $volumetric_weight = $shipping_package['cubic'] > 0 ? $shipping_package['cubic'] / 6000 : 0; //Canada Post: (L cm x W cm x H cm)/6000
                        // Use the largest value of total weight or volumetric/dimensional weight
                        $shipping_weight = ($package_weight <= $volumetric_weight && $volumetric_weight <= $max_cp_box['weight']) ? $volumetric_weight : $package_weight;
                    } else {
                        $shipping_weight = $package_weight;
                    }
                    // Envelope weight with bill/notes/advertising inserts: ex. 20g
                    $shipping_weight += (!empty($this->options->packageweight) ? floatval($this->options->packageweight) : 0);

                    // Update weight
                    $containers[$i]['actual_weight'] = $shipping_package['weight'];
                    $containers[$i]['weight'] = $shipping_weight;
                    $limit_services = null;
                    if (isset($this->options->rules_enable) && $this->options->rules_enable){
                        $limit_services = $this->get_limited_services($shipping_package['products']);
                        if (in_array('CP.NONE', $limit_services)) {
                            // Oops, not supposed to ship with this method.
                            $rates = null;
                            break;
                        }
                    }
        
                    $shipping_weight = round($shipping_weight,2); // 2 decimal places.
                    $length = round($shipping_package['length'], 2);
                    $width = round($shipping_package['width'], 2);
                    $height = round($shipping_package['height'], 2);
        
                    // Debug
                    if ($this->options->log_enable){
                        $this->log->params[] = array('country'=>$country, 'state'=>$state, 'postal'=>$postal, 'shipping_weight'=>$shipping_weight, 'package_weight'=>$package_weight, 'length'=>$length, 'width'=>$width, 'height'=>$height,
                            'box_name'=>(isset($shipping_package['box_name']) ? $shipping_package['box_name'] : ''), 'box_weight'=>(isset($shipping_package['box_weight']) ? floatval($shipping_package['box_weight']) : ''),  'box_margin'=>(isset($shipping_package['box_margin']) ? floatval($shipping_package['box_margin']) : ''), 
                                                    'source_postalcode'=> $this->options->source_postalcode, 
                                                    'shipping_class'=>$this->get_product_shipping_classes($shipping_package['products']), 'product_count' => $this->product_count($shipping_package['products']), 'limited_services'=> $limit_services);
                    }

                    // In subsequent iterations, use Rates from a previous package/container that is identical to current container. Else do a rates lookup.
                    $result_previous = $this->get_rates_previous($i, $containers);
        
                    if ($result_previous != null) {
                        $rates[] = $result_previous;
                    } else {
                        $cp_lookups++;
                        if ($cp_lookups > $max_cp_lookups){
                            // Error. Protect from too many lookups.
                            if ($this->options->log_enable){
                                $this->log->info[] = "Api limit protection.";
                            }
                            $rates = null;
                            break;
                        }
                        if ($cp_lookups > 0 && $api_limit > 0){
                            // Throttle Limit:
                            usleep($api_limit);
                        }
        
                        // WebServices Lookup
                        $result = $this->get_rates($country, $state, $city, $postal, $shipping_weight, $length, $width, $height, $limit_services);
                        $rates[] = $result;
                        if (empty($result)){
                            // Rate was not found: Usually because package is too big or api error. Can't display because it would cause an incorrect cost.
                            if ($this->options->log_enable){
                                $this->log->info[] = "Rate was not found: Services enabled did not have a valid package size or Api error.";
                            }
                            $rates = null;
                            break;
                        }
                    }
                    // Save to container
                    $containers[$i]['rate'] = $result;
                    // Save distinct rate service_codes.
                    $distinct_service_codes = $this->get_distinct_service_codes($distinct_service_codes, $result);
                }
                    
                if (!empty($rates)){
                    // Combine rates result sets into $rates_combined
        
                    if (count($rates) > 1 && count($rates[0]) > 0){
                        // Loop through rates by type, combine to get total cost/type array().
                            
                        // Add first rate to $rates_combined.
                        for($j=0;$j<count($rates[0]);$j++){
                            $rates_combined[] = clone $rates[0][$j];
                        }
                            
                        // Now loop through the remainder of the rates array to create aggragate sum of prices.
                        for($i=1;$i<count($rates);$i++){
                            // Loop over objects.
                            for($j=0;$j<count($rates[$i]);$j++){
                                // Match using Service Code
                                for($r=0; $r<count($rates_combined); $r++){
                                    if ($rates_combined[$r]->service_code == $rates[$i][$j]->service_code){
                                        // Sum Rate price
                                        $rates_combined[$r]->price = floatval($rates_combined[$r]->price) + floatval($rates[$i][$j]->price);
                                    }
                                } // end foreach
                            }// end for
                        }// end for
                            
                        // Now loop through the $rates_combined array and only keep the rates that are in distinct_service_codes.
                        if (!empty($distinct_service_codes)){
                            for ($i=0;$i<count($rates_combined);$i++){
                                // Check for valid service codes.
                                if (!in_array($rates_combined[$i]->service_code, $distinct_service_codes)) {
                                    // oops, this service_code is not one of the distinct_service_codes. It has not been an aggragate sum so it needs to be removed.
                                    unset($rates_combined[$i]);
                                }
                            }
                        }
                            
                    } else {
                        // Only 1 rates result set
                        $rates_combined = $rates[0];
                    }

                    // Limit Rates
                    $limit_sorted = false;
                    // If services are the same cost, only keep the better service. (ie. Regular Parcel vs Epedited Parcel same cost)
                    if ($this->options->prefer_service===true && count($rates_combined) > 1){
                        $better_service = array_keys($this->available_services); // These are ordered by lowest to best already.
                        $prev_cost = 0;
                        $prev_service_code = '';
                        // sort rates by lowest cost.
                        usort($rates_combined, array(&$this, 'sort_rate_services'));
                        $limit_sorted = true;
                        // Loop from lowest to highest cost.
                        for($i=0;$i<count($rates_combined);$i++){
                            if ($rates_combined[$i]->price == $prev_cost){
                                // Check their service_code position in the $better_service array.
                                if (array_search($rates_combined[$i]->service_code, $better_service) > array_search($prev_service_code, $better_service)){
                                    // Remove the 'lower' service because it has the same cost.
                                    unset($rates_combined[$prev_index]);
                                } else {
                                    unset($rates_combined[$i]);
                                    continue;
                                }
                            }
                            $prev_cost = $rates_combined[$i]->price;
                            $prev_service_code = $rates_combined[$i]->service_code;
                            $prev_index = $i;
                        }
                            
                    }
                    
                    $limit_rates =  !empty($this->options->limit_rates) && intval($this->options->limit_rates) > 0 ? intval($this->options->limit_rates) : 0;
                    
                    if ($this->options->log_enable){
                        // Add information about calculations
                        if(!empty($this->options->exchange_rate) && floatval($this->options->exchange_rate) != 0){
                        $this->log->info[] = "Exchange Rate applied: " . floatval($this->options->exchange_rate);
                        }
                        if (!empty($this->options->margin) && $this->options->margin != 0) {
                            $this->log->info[] = 'Margin: ' . $this->options->margin . '%'; //Add margin
                        }
                        if (!empty($this->options->margin_value) && $this->options->margin_value != 0) {
                            $this->log->info[] = 'Margin Value: '. $this->options->margin_value;
                        }
                        if ($limit_rates > 0){
                            $this->log->info[] = 'Limiting number of services to: '. $limit_rates . ' services displayed';
                        }
                    }
                    
                    
        
                    // Do final foreach($rates_combined)
                    $ratecount = 0;
                    foreach($rates_combined as $rate){
                        $ratecount++;
                        if ($limit_rates > 0 && $limit_rates <= 8 && $ratecount > $limit_rates){
                            break; // limit displayed rates.
                        }
                        if (!empty($this->options->exchange_rate) && floatval($this->options->exchange_rate) != 0) {
                            $rate->price = $rate->price * floatval($this->options->exchange_rate); //Adjust using exchange_rate (It's really margin)
                        }
                        if (!empty($this->options->service_margin) && is_array($this->options->service_margin) && !empty($this->options->service_margin[$rate->service_code])){
                            // Margin for specific services
                            $margin_value = $this->options->service_margin[$rate->service_code];
                            $margin_percent = (!empty($margin_value) && substr($margin_value, -1) == '%');
                            if ($margin_percent){ $margin_value = str_replace('%','', $margin_value); }
                            $margin_value = floatval($margin_value);
                            if ($margin_value != 0){
                                // Apply service-specific margin
                                if ($margin_percent){
                                    $rate->price = $rate->price * (1 + $margin_value/100); //Add margin percent
                                } else {
                                    $rate->price = $rate->price + $margin_value; //Add margin value
                                }
                                if ($rate->price < 0){ $rate->price = 0; }
                                if ($this->options->log_enable){
                                    $this->log->info[] = 'Margin applied for ' . $rate->service . ': ' . $margin_value . ($margin_percent ? '%' : ''); //log margin
                                }
                            }
                        }

                        if (!empty($this->options->margin) && $this->options->margin != 0) {
                            $rate->price = $rate->price * (1 + $this->options->margin/100); //Add margin
                        }
                        if (!empty($this->options->margin_value) && $this->options->margin_value != 0) {
                            $rate->price = $rate->price + $this->options->margin_value; //Add margin_value
                            if ($rate->price < 0){ $rate->price = 0; }
                        }
                            
                        $box_margin = $this->get_box_margin_sum($containers);
                        if ($box_margin != 0){
                            $rate->price = $rate->price + $box_margin; //Add box margin if any value exists.
                            if ($rate->price < 0){ $rate->price = 0; }
                        }
                            
                        $delivery_label = '';
                        if (!empty($this->options->delivery) && $rate->expected_delivery != '') { 
                            $delivery_label =  ' (' . (!empty($this->options->delivery_label) ? wc_clean($this->options->delivery_label) :  __('Delivered by', 'woocommerce-canadapost-webservice')) . ' ' . $rate->expected_delivery . ')';
                            if (isset($rate->guaranteed) && ($rate->guaranteed == false) && isset($this->options->delivery_guarantee) && $this->options->delivery_guarantee) {
                                $delivery_label = ''; // only display Delivery label on Guaranteed services (when $this->options->delivery_guarantee is enabled).
                            }
                        }
                            
                        $rateitem = array(
                            'id' 		=> $this->rate_id($rate->service_code),
                            'label' 	=> $rate->service . $delivery_label,
                            'cost' 		=> $rate->price,
                            'package'   => $package
                        );
                        // Register the rate
                        $this->add_rate( $rateitem );
                            
                    }
                    
                } // endif
        
                // Lettermail Limits.
                if ($this->options->lettermail_limits && !empty($this->options->lettermail_maxlength) && !empty($this->options->lettermail_maxwidth) && !empty($this->options->lettermail_maxheight)) {
                    // Check to see if within lettermail limits.
                    $lettermail_cubic =  $this->options->lettermail_maxlength * $this->options->lettermail_maxwidth * $this->options->lettermail_maxheight;
                    if ($lettermail_cubic > 0) {
                        if ($max['length'] <= $this->options->lettermail_maxlength && $max['width'] <= $this->options->lettermail_maxwidth && $max['height'] <= $this->options->lettermail_maxheight
                            && $cubic <= $lettermail_cubic) {
                                // valid, within limit.
                            } else {
                                // over limit. Disable lettermail rates from being applied.
                                $this->options->lettermail_enable = false;
                                $this->options->altrates_enable = false;
                            }
                    }
                }
        
                if ($this->options->lettermail_enable || (empty($rates) && $this->options->altrates_enable)){
                    /*
                    Letter-post / Flat Rates
                    */
                    $shipping_classes = array_unique(array_column($products,'class'));
                    // Check for shipping class rules that limit the use of lettermail/flat rates for the cart product's shipping classes
                    $class_rules_enabled = isset($this->options->rules_enable) && $this->options->rules_enable;
                    if (!$class_rules_enabled || !$this->class_rules_match_exists($shipping_classes, array('CP.NOLM','CP.NONE'))){
                        // If override packing weight, remove package weight and add custom package weight.
                        if (!empty($this->options->lettermail_override_weight) && $this->options->lettermail_override_weight){
                            $total_weight -= (!empty($this->options->packageweight) ? floatval($this->options->packageweight) : 0);
                            $total_weight += (!empty($this->options->lettermail_packageweight) ? floatval($this->options->lettermail_packageweight) : 0);
                            $total_weight = round($total_weight,2); // 2 decimal places.
                        }
                        $woocommerce = WC();
                        // Subtotal for lettermail min/max subtotal calculation.  (get_subtotal from wc 3.2)
                        $cart_subtotal = method_exists($woocommerce->cart,'get_subtotal') ? $woocommerce->cart->get_subtotal() + $woocommerce->cart->get_subtotal_tax() : $woocommerce->cart->subtotal;
                        if ($this->options->lettermail_exclude_tax){
                            $cart_subtotal = method_exists($woocommerce->cart,'get_subtotal') ? $woocommerce->cart->get_subtotal() : $woocommerce->cart->subtotal_ex_tax;
                        }
            
                        foreach($this->lettermail as $index => $lettermail) {
                            if ($total_weight >= $lettermail['weight_from'] && $total_weight < $lettermail['weight_to']
                                && ($country == $lettermail['country'] || ($lettermail['country']=='INT' && $country!='CA' && $country !='US'))
                                && (empty($lettermail['prov']) || $state ==  $lettermail['prov'])
                                && (empty($lettermail['max_qty']) || $total_quantity <=  $lettermail['max_qty'])
                                && (empty($lettermail['min_total']) ||  $cart_subtotal >= $lettermail['min_total'])
                                && (empty($lettermail['max_total']) ||  $cart_subtotal <= $lettermail['max_total'])
                                && (empty($lettermail['shipping_class']) || (is_array($lettermail['shipping_class']) 
                                    && count(array_diff($shipping_classes, $lettermail['shipping_class'])) == 0))
                            ){
                                $lettermail_rateid = apply_filters('cpwebservice_lettermail_prefix', 'LM'.$index, $lettermail['label']); // Previously 'Lettermail '.$lettermail['label']
                                $rateitem = array(
                                    'id' 		=> $this->rate_id($lettermail_rateid),
                                    'label' 	=> $lettermail['label'],
                                    'cost' 		=> $lettermail['cost'],
                                    'package'   => $package
                                );
                                $this->add_rate( $rateitem );
                            }
                        }
                    }
                }
                    
                // Save shipping info to save with order to session.
                $shipping_info = array('rates'=>$rates_combined, 'packages'=>$containers, 'origin_postalcode'=>$this->options->source_postalcode);
                do_action('cpwebservice_order_shipping_info', $shipping_info);
        
                // Sort rates (by lowest cost)
                if(!empty($this->rates)){
                    // Sort associated array.
                    uasort($this->rates, array(&$this, 'sort_rates'));
                }
                
                // Filter Rates
                // Custom Rates Filter/Hook
                $this->rates = apply_filters('cpwebservice_rates', $this->rates);
            }
	    }
	    // Logging
	    if ( $this->options->log_enable ){
	        $this->log->rates = $this->rates;
	        $this->log->datestamp = current_time('timestamp');
	        // Save to transient for 20 minutes.
	        set_transient( 'cpwebservice_log', $this->log, 20 * MINUTE_IN_SECONDS );
	    }
	
	}
    
    // Checks to see if commercial services are activated
    function hide_service($service_code){
        // If commercial services are inactive and this service code is in the commercial_services array, which is not empty.
        return empty($this->options->contractid) && !empty($this->commercial_services) && in_array($service_code,  $this->commercial_services);
    }

	// Sort Rates function
	function sort_rates($a, $b){
	    if ($a->cost == $b->cost) {
	        return 0;
	    }
	    return ($a->cost < $b->cost) ? -1 : 1;
	}
	
	// Sort rate services function
	function sort_rate_services($a, $b){
	    if ($a->price == $b->price) {
	        return 0;
	    }
	    return ($a->price < $b->price) ? -1 : 1;
	}
	
	// Sort Products function.  Descending (biggest box first)
	function sort_products($a, $b){
	    $a_max = max($a['length'], $a['width'], $a['height']);
	    $b_max = max($b['length'], $b['width'], $b['height']);
	    if ($a['cubic'] == $b['cubic'] && $a_max == $b_max) {
	        return 0;
	    }
	    if ($a['cubic'] == $b['cubic'] && $a_max < $b_max) {
	        return 1;
	    }
	    return ($a['cubic'] < $b['cubic']) ? 1 : -1;
	}
	
	
	// Gets distinct service codes.
	// @unique array
	function get_distinct_service_codes($unique, $results){
	    $service_codes = array();
	    if (is_array($results)){
	        for($i=0;$i<count($results);$i++){
	            if (!in_array($results[$i]->service_code, $service_codes)){
	                $service_codes[] = $results[$i]->service_code;
	            }
	        }
	    }
	    if (empty($unique)){ // if empty, this is the first $results set.
	        return $service_codes;
	    } else {
	        // Go through $unique array and remove any service_code not found.
	        for ($i=0;$i<count($unique);$i++){
	            if (!in_array($unique[$i], $service_codes)){
	                unset($unique[$i]);
	            }
	        }
	        	
	        return $unique;
	    }
	}
	
	function get_rates_previous($index, $containers){
	    $rate = null;
	    // after the 1st item.
	    if ($index > 0){
	        // Get Package/Container unique features.
	        $weight_kg = $containers[$index]['weight'];
	        $length = $containers[$index]['length'];
	        $width = $containers[$index]['width'];
	        $height = $containers[$index]['height'];
	        $cubic = $containers[$index]['cubic'];
	
	        // Look for previous container that is identical (and has rates -- after all, that's what we're looking for).
	        for($j=0;$j<$index;$j++){
	            if ($containers[$j]['weight'] == $weight_kg && $containers[$j]['length'] == $length && $containers[$j]['width'] == $width
	                &&  $containers[$j]['height'] == $height && $containers[$j]['cubic'] == $cubic && $containers[$j]['rate']!=null){
	                // Huston, we have a match.
	                return $containers[$j]['rate'];
	            }
	        }
	        	
	    }
	    return $rate;
	}
	
	function get_box_margin_sum($containers){
	    $margin = 0;
	    foreach($containers as $container){
	        if (isset($container['box_margin']) && floatval($container['box_margin']) != 0){
	            $margin += floatval($container['box_margin']);
	        }
	    }
	    return $margin;
    }
    
    private function get_product_altrates_default(){
        if (!empty($this->options->altrates_defaults) && $this->options->altrates_defaults){
            return array('weight'=> !empty($this->options->altrates_weight) ? floatval($this->options->altrates_weight) : 0,
                'length'=>!empty($this->options->altrates_length) ? floatval($this->options->altrates_length) : 0,
                'width'=> !empty($this->options->altrates_width) ? floatval($this->options->altrates_width): 0,
                'height'=> !empty($this->options->altrates_height) ? floatval($this->options->altrates_height) : 0);
        }
        return array('weight'=>0,'length'=>0,'width'=>0,'height'=>0);
    }
	
	// Format Delivery date from API.
	public function format_expected_delivery($expected_delivery){
	    if (!empty($expected_delivery) && !empty($this->options->delivery_format) && 
	        ($this->options->delivery_format == 'D M j, Y' || $this->options->delivery_format == 'F j, Y' || $this->options->delivery_format == 'M j, Y' || $this->options->delivery_format == 'M j' || $this->options->delivery_format == 'l M j, Y' || $this->options->delivery_format == 'D j M Y')){
	        // Try to parse time.
	       if (($expected_delivery_time = DateTime::createFromFormat('Y-m-d', $expected_delivery )) !== false ){
	            // Wordpress Date-formatted return.
	           if ($this->options->delivery_format == 'D M j, Y' || $this->options->delivery_format == 'F j, Y' || $this->options->delivery_format == 'M j, Y' || $this->options->delivery_format == 'M j' || $this->options->delivery_format == 'l M j, Y' || $this->options->delivery_format == 'D j M Y'){
	               return date_i18n( $this->options->delivery_format , $expected_delivery_time->getTimestamp() );
	            } else { 
	                return date( 'Y-m-d', $expected_delivery_time->getTimestamp() );
	            }
	        }
	    }
	    return $expected_delivery;
	}

	
	public function get_limited_services($products){
	    $services = array();
	    $services_rules = array();
	    if (isset($this->rules) && is_array($this->rules) && count($this->rules) > 0){
	        // Check Products that are in a Shipping Class.
	        $term_list = $this->get_product_shipping_classes($products);
            if (!empty($term_list) && is_array($term_list)){
                // In Shipping Class. Check rules table.
                foreach($this->rules as $rule){
                    if (isset($rule['shipping_class']) && isset($rule['services']) && is_array($rule['services'])){
                        if (in_array($rule['shipping_class'], $term_list)){
                            // Shipping Class matches a rule.
                            $services_rules[] = $rule['services'];
                        }
                    }
                }
            }
	    }
	    if (count($services_rules) > 1){
	        //Only use values that are present in _all_ rules.  array_intersect all rule arrays.
	        $services=call_user_func_array('array_intersect', $services_rules);
	    } elseif (count($services_rules) == 1){
	        $services= $services_rules[0];
	    }
	    return $services;
	}

    private function class_rules_match_exists($shipping_classes, $service_codes) {
        if (!empty($this->rules) && is_array($this->rules) && !empty($shipping_classes)){
            // In Shipping Class. Check rules table.
            foreach($this->rules as $rule){
                foreach($service_codes as $service_code){
                    if (isset($rule['shipping_class']) && isset($rule['services']) && is_array($rule['services'])
                        && in_array($service_code, $rule['services']) // Ex. CP.NOLM or CP.NONE
                        && in_array($rule['shipping_class'], $shipping_classes)
                    ){
                    return true;
                    }
                }
            }
        }
        return false;
    }
		
	// Get item_ids into an easy-to-use array.
	private function get_product_ids($products){
	    $ids = array();
	    foreach($products as $level){
	        foreach($level as $p){
	            if (isset($p['item_id'])){
	                $ids[] = $p['item_id'];
	            }
	        }
	    }
	    $ids = array_unique($ids);
	    return $ids;
	}
	
    private function get_product_shipping_classes($products){
	    $class_ids = array();
	    foreach($products as $level){
	        foreach($level as $p){
	            if (isset($p['class'])){
	                $class_ids[] = intval($p['class']);
	            }
	        }
	    }
	    $ids = array_unique($class_ids);
	    return $ids;
    }
	
	// Used for logging
	private function product_count($products){
	    $i = 0;
	    foreach($products as $level){
	        $i += count($level);
	    }
	    return $i;
	}
	
	// Param package arrays and returns an array of any products set to specific warehouses.
	private function get_product_warehouses($containers){
	    $product_ids = array();
	    foreach($containers as $container){
	       $product_ids += $this->get_product_ids($container['products']);
	    }
	    $product_ids = array_unique($product_ids);
	    $warehouses = array();
	    foreach($product_ids as $id){
	        $product_warehouse = get_post_meta( $id, '_cpwebservice_product_warehouse', true );
	        if (!empty($product_warehouse) && is_array($product_warehouse)){
	            
	            $warehouses[$id] = $product_warehouse;
	            if (empty($warehouses['summary'])){ $warehouses['summary'] = $product_warehouse; }
	            else {
	                // Only keep unique address_index_ids
	               $warehouses['summary'] = array_unique( $warehouses['summary'] + $product_warehouse );
	            }
	        }
	    }
	    return $warehouses;
	}
	
	// Provides a valid list of origins for given limit_warehouse array.
	private function limit_product_warehouse_origin($origin, $limit_warehouse, $address) 
	{
	    // Only limit if there are warehouses defined for a given product.
	    if (!empty($limit_warehouse) && !empty($limit_warehouse['summary'])){
    	    foreach($address as $id => $a){
    	        
    	        if (isset($a['origin']) && $a['origin'] && !empty($a['postalcode']) && isset($a['postalcode_lat']) && isset($a['postalcode_lng'])){
    	            if (!in_array($id.'_'.$a['postalcode'], $limit_warehouse['summary']) ){
    	                 // Origin is invalid for a certain product.
    	                 unset($origin[$a['postalcode']] );
    	            }
    	        }
    	    }
	    }
	    return $origin;
	}
	
	// Converts array of 3 dimensions to $box[] associated array. 
	public function to_box($dimensions, $unit='cm'){
	    if (!empty($dimensions) && is_array($dimensions) && count($dimensions)==3){
	        if ($unit!='cm') { 
	            $dimensions[0] = cpwebservice_resources::convert($dimensions[0], $unit, 'cm'); 
	            $dimensions[1] = cpwebservice_resources::convert($dimensions[1], $unit, 'cm');
	            $dimensions[2] = cpwebservice_resources::convert($dimensions[2], $unit, 'cm');
	        }
	        $box = array('length' => $dimensions[0], 'width'=> $dimensions[1], 'height'=>$dimensions[2], 'cubic'=>($dimensions[0]*$dimensions[1]*$dimensions[2]));
	        return $box;
	    }
	    return array();
	}
	
	
	/**
	 * Calculates girth from rectangular package dimensions.
	 * @param number $length
	 * @param number $width
	 * @param number $height
	 * @return number girth
	 */
	function calc_girth($length,$width, $height){
	
	    $longest = max($length,$width, $height);
	
	    // Longest side is not included in girth, as it is perpendicular.
	    if ($longest == $length){
	        return $width * 2 + $height * 2;
	    }else if ($longest == $width){
	        return $length * 2 + $height * 2;
	    } else { //$longest == $height
	        return $width * 2 + $length * 2;
	    }
	}
	
	/**
	 * Returns a rate ID based on this methods ID and instance, with an optional
	 * suffix if distinguishing between multiple rates.
	 */
	public function rate_id( $suffix = '' ) {
	    if (method_exists($this, 'get_rate_id')){
	        return $this->get_rate_id($suffix);
	    }
	    // get_rate_id
	    $rate_id = array( $this->id );
	    if ( $this->instance_id ) {
	        $rate_id[] = $this->instance_id;
	    }
	    if ( $suffix ) {
	        $rate_id[] = $suffix;
	    }
	    return implode( ':', $rate_id );
	}
		
	// Get service code part of field name.
	public function get_service_code_field($service_code){
	    return strtolower(preg_replace("/[^A-Za-z0-9]/", '', $service_code));
	}
	
	// Gets service name
	public function service_name_label($service_code, $service_label){
	    if(!empty($this->service_labels) && !empty($this->service_labels[$service_code])){
	        return wc_clean($this->service_labels[$service_code]);
	    }
	    // Return supplied label.
	    return $service_label;
	}

    public function get_service_code($service_label){
        $service_code = '';
        if(!empty($this->service_labels)){
            $service_code = array_search($service_label, $this->service_labels);
        }
        if (empty($service_code)){
            $service_code = array_search($service_label, $this->available_services);
        }
        return !empty($service_code) ? $service_code : '';
    }

    public function get_service_label($service_code){
        $service_label = '';
        if(!empty($this->service_labels) && !empty($this->service_labels[$service_code])){
            $service_label = $this->service_labels[$service_code];
        }
        if (isset($this->available_services[$service_code])){
            $service_label = $this->available_services[$service_code];
        }
        return $service_label;
    }
	
    public function wpml_woocommerce_init() {
	    global $sitepress, $woocommerce_wpml; 
	    if (isset($sitepress) && isset($woocommerce_wpml)){ // only when delivery dates are there.  && !empty($this->options->delivery)
    	    // WPML translates Method labels but unfortunately, Delivery dates are in them! 
    	    // This will override the filter, so that this plugin's labels can be skipped from being translated.
    	    remove_filter('woocommerce_package_rates', array( $woocommerce_wpml->shipping , 'translate_shipping_methods_in_package'));
    	    //add_filter('woocommerce_package_rates', array($this, 'translate_shipping_methods_in_package'));
	    }
	}
	// Not used.
	public function translate_shipping_methods_in_package( $available_methods ){
	    global $sitepress;
	    if (isset($sitepress)){
	        $language = $sitepress->get_current_language();
	        if( $language == 'all' ){ $language = $sitepress->get_default_language(); }
	        foreach($available_methods as $shipping_id => $method){
    	        $shipping_id = str_replace( ':', '', $shipping_id );
    	        // Skip this plugin's shipping methods
    	        if (0 !== strpos($shipping_id, $this->id)){
    	           $available_methods[$shipping_id]->label = apply_filters( 'wpml_translate_single_string', $available_methods[$shipping_id]->label, 'woocommerce', $shipping_id .'_shipping_method_title', $language);
    	        }
	       }
	    }
	    return $available_methods;
	}
	
	/*
	 * Provide a placeholder postal code for
	 * countries that do not have postal/zip codes.
	 */
	public function postal_code_required($country, $postalcode){
	    if (empty($postalcode)){
	        // Countries without a postal code required. As discussed on https://gist.github.com/kennwilson/3902548#gistcomment-2045065
    	    $countriesNoPostal = array("AO", "AG", "AW", "BS", "BZ", "BJ", "BW", "BF", "BI", "CM", "CF", "KM", "CG", "CD", "CK", "CI", "DJ", "DM", "GQ", "ER", "FJ", "TF", "GM", "GH", "GD", "GN", "GY", "HK", "JM", "KE", "KI", "MO", "MW", "ML", "MR", "MU", "MS", "NR", "AN", "NU", "PA", "QA", "RW", "KN", "LC", "ST", "SA", "SC", "SL", "SB", "SO", "ZA", "SR", "SY", "TZ",  "TL", "TK", "TO", "TT", "TV", "UG", "AE", "VU", "YE", "ZW");
    	    if (in_array($country, $countriesNoPostal)){
    	        // Provide a placeholder postal code for lookup, since it is required for webservices.
    	        $postalcode = '1';
    	    }
	    }
	    return $postalcode;
	}
	
	// Section: Size Display Options
	
	// Display Size (as option determines)
	public function display_unit($cm){
	    return cpwebservice_resources::display_unit($cm, $this->options->display_units);
	}
	
	public function display_unit_cubed($cm3){
	    return cpwebservice_resources::display_unit_cubed($cm3, $this->options->display_units);
	}
	// Display Weight
	public function display_weight($kg){
	    return cpwebservice_resources::display_weight($kg, $this->options->display_weights);
	}
	
	// Save Size (to cm)
	// Returns cm
	public function save_unit($size){
	    return cpwebservice_resources::save_unit($size, $this->options->display_units);
	}
	
	// Save weight (to kg)
	// Returns kg.
	public function save_weight($weight){
	    return cpwebservice_resources::save_weight($weight, $this->options->display_weights);
	}
	
	// END Section Size/Weight Display Options.
	
}