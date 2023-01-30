<?php
/*
 Order Details class
orderdetails.php

Copyright (c) 2013-2022 Jamez Picard

*/
abstract class cpwebservice_orderdetails
{
	
	public $method_id = 'woocommerce_cpwebservice';
    
	protected $shipment_address = null;

    protected $db;
    protected $shippingmethod;
    protected $options;
    protected $dbmsg;
	
	/**
	 * __construct function.
	 *
	 * @access public
	 * @return woocommerce_cpwebservice_orderdetails
	 */
	function __construct() {

		$this->init();
	}
	
	/*
	 * Init
	 */ 
	function init() {
	    $default_options = array('enabled'=>'no','shipments_enabled'=>true, 'geolocate_origin'=>true, 'geolocate_limit'=>false, 'display_units'=>'cm', 'display_weights'=>'kg','legacyshipping'=>true);
		$this->options = get_option('woocommerce_cpwebservice', $default_options);
		$this->options =	(object) array_merge((array) $default_options, (array) $this->options); // ensure all keys exist, as defined in default_options.
        if ($this->get_resource('shipments_implemented')===false) { $this->options->shipments_enabled = false; }
        $this->options->legacyshipping = apply_filters('cpwebservice_legacy_shipping', $this->options->legacyshipping);
        $this->db = new woocommerce_cpwebservice_db();
        $this->shippingmethod   = null; // init when needed.
		if ($this->options->enabled){
			// Wire up actions
			if (is_admin()){
			    $this->shipment_address = (array)get_option('woocommerce_cpwebservice_shipment_address', array());
				add_action( 'add_meta_boxes', array(&$this, 'add_shipping_details_box') );
				wp_enqueue_script( 'cpwebservice_admin_orders' ,plugins_url( 'framework/lib/admin-orders.js' , dirname(__FILE__) ) , array( 'jquery' ), $this->get_resource('version') );
				wp_localize_script( 'cpwebservice_admin_orders', 'cpwebservice_admin_orders', array( 'confirm'=>__('Are you sure you wish to delete?', 'woocommerce-canadapost-webservice') ) );
				wp_enqueue_script( 'cpwebservice_modal' ,plugins_url( 'framework/lib/modal.js' , dirname(__FILE__) ) , array( 'jquery' ), $this->get_resource('version') );
				wp_localize_script( 'cpwebservice_modal', 'cpwebservice_order_actions', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ), 'postNonce' => wp_create_nonce( 'cpwebservice_order_actions' ), 'removeNonce' => wp_create_nonce( 'cpwebservice_shipment_remove' ), 'removePackageNonce' => wp_create_nonce( 'cpwebservice_package_remove' ), 'confirm' => __('Are you sure you wish to remove this draft Shipment Package?', 'woocommerce-canadapost-webservice')) );
				add_action( 'wp_ajax_cpwebservice_order_actions' , array(&$this, 'order_actions_ajax')  );
                add_action( 'wp_ajax_cpwebservice_package_remove' , array(&$this, 'package_remove')  );
                add_action('wp_ajax_cpwebservice_migrate_shipping_legacy_data', array(&$this, 'migrate_shipping_legacy_data'));
				// Display Units (only in/lb and cm/kg supported).
				$dimension_unit                = get_option( 'woocommerce_dimension_unit' );
				$this->options->display_units  = $dimension_unit == 'in' ? 'in' : 'cm';
				$weight_unit                   = get_option( 'woocommerce_weight_unit' );
				$this->options->display_weights= $weight_unit == 'lbs' ? 'lbs' : 'kg';
                $this->dbmsg = get_transient( 'cpwebservice_db_msg' );
                if (!empty($this->dbmsg) && is_array($this->dbmsg)){
                    add_action( 'wp_ajax_cpwebservice_admin_notice_clear' , array(&$this, 'admin_notice_dbmsg_clear')  );
                    add_action( 'admin_notices',  array(&$this, 'admin_notice_dbmsg' ));
                }
			} else {
				add_action( 'cpwebservice_order_shipping_info', array(&$this, 'order_shipping_info'), 10, 1 );
				add_action( 'woocommerce_cart_emptied', array(&$this, 'order_shipping_info_reset'));
			
				if (version_compare( WC()->version, '3.0.0', '>=' )){ //if (class_exists('WC_Order_Item_Data_Store')) {  //WC 3.x ; 
				    add_action( 'woocommerce_checkout_create_order_shipping_item', array(&$this, 'checkout_order_add_shipping'), 10, 4 );
				    add_action( 'woocommerce_checkout_order_processed', array(&$this, 'order_processed') , 10, 3);
				} else {
			     	add_action( 'woocommerce_order_add_shipping', array(&$this, 'order_add_shipping'), 10, 3 );
				}
			}
		}
	}
	
	/*
	 * Return resources
	 */
	abstract function get_resource($id);
    
    // Get shipment data.
    public function get_shipping_info($order_id){
        $shipping_info = $this->db->shipments_get($order_id);
        if (empty($shipping_info) && $this->options->legacyshipping){
            $shipping_info = $this->get_shipping_info_legacy($order_id);
        }
        return $shipping_info;
    }
    // Get Legacy shipment data. Auto-migrates data.
    public function get_shipping_info_legacy($order_id){
        $shipping_info = $this->db->shipments_get_legacy($order_id);
        if (!empty($shipping_info)){
            $shipping_info = $this->shipments_legacy_orderdata($order_id, $shipping_info);
            foreach($shipping_info as $package_index => $info){
                $this->db->shipment_save($order_id, $info, $package_index);
            }
            if ($this->db->no_errors()){
                $this->db->shipments_remove_legacy($order_id);
            }
        }
        return $shipping_info;
    }
    // If exists.
    public function shipments_exist($order_id){
        $shipping_exists = $this->db->shipments_exist($order_id);
        if (!$shipping_exists && $this->options->legacyshipping){
            $shipping_exists = $this->db->shipments_exist_legacy($order_id);
        }
        return $shipping_exists;
    }
    
    /* 
	 * Shipping rate data saved in session
	 */
	function order_shipping_info($shipping_info = array()){
		// Keep $package in session.
		WC()->session->set( 'cpwebservice_shipping_info', $shipping_info );
	}
	
	/*
	 * Hooks: do_action( 'woocommerce_checkout_create_order_shipping_item', $item, $package_key, $package, $order );
	 * Since WC 3.x
	 */
	function checkout_order_add_shipping( $item, $package_key, $package = null, $order = null){
	    // Get Shipping_Rate information
        $id = $item->get_method_id();
        $method_id = explode(':', $id);
        if (count($method_id) == 1){ // ex. Method ID now (woocommerce_cpwebservice) instead of (woocommerce_cpwebservice:1:DOM.XP) or (woocommerce_cpwebservice:DOM.XP)
            // $chosen_shipping_methods (["woocommerce_cpwebservice:7:DOM.XP"])
	        $chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods' );
            if (isset($chosen_shipping_methods) && is_array($chosen_shipping_methods) && !empty($chosen_shipping_methods[$package_key])){
                $id = $chosen_shipping_methods[$package_key];
                $method_id = explode(':', $id);
            }
	    }

	    $selected_rate = new stdClass();
	    // Method ID (ex. woocommerce_cpwebservice:1:DOM.XP)
	    $selected_rate->id = $id;
	    if (count($method_id) > 0){
	        $selected_rate->method_id = $method_id[0];
            if (count($method_id) > 1){
                $selected_rate->service_code = $method_id[count($method_id)-1];
            }
	    }
	    
	    if ($selected_rate->method_id == $this->method_id){
	        // Save to session
	        WC()->session->set( 'cpwebservice_shipping_selected_rate', $selected_rate );
	    }
	}
	/*
	 * Hooks: do_action( 'woocommerce_checkout_order_processed', $order_id, $posted_data, $order );
	 * Since WC 3.x
	 */
	function order_processed( $order_id, $posted_data, $order = null){
	    // Retrieve method id used from session.
        // $chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods' );
        $selected_rate = WC()->session->get('cpwebservice_shipping_selected_rate');
	    if (isset($selected_rate) && is_object($selected_rate)){
	       // Save shipping information.
	        $this->order_add_shipping($order_id, 0, $selected_rate);
	    }
	}
	
	/*
	 Hooks: //do_action( 'woocommerce_order_add_shipping', $this->id, $item_id, $selected_rate );
	 */
	function order_add_shipping($id, $item_id, $selected_rate) {
		
		// Only process data if the order is placed with this shipping method.
		if ($selected_rate->method_id == $this->method_id){
			// Retrieve from session
			$shipping_info = WC()->session->get( 'cpwebservice_shipping_info' );
			if (!empty($shipping_info) && is_array($shipping_info)){
                try {
                    // Add selected shipping.
                    if (isset($shipping_info['rates']) && is_array($shipping_info['rates'])) {
                        foreach($shipping_info['rates'] as $i=>$rate){
                            if ($rate->service_code == $selected_rate->service_code) {
                                $selected_rate = $rate;
                                // Move selected rate to beginning of array
                                unset($shipping_info['rates'][$i]);
                                break;
                            }
                        }
                        // Completing the move of the selected rate to beginning of array
                        array_unshift($shipping_info['rates'], $selected_rate);
                    }
                    else 
                    {
                        //Save selected rate used.  
                        $shipping_info['rates'] = array($selected_rate);
                    }
                    $shipping_info['service_code'] = isset($selected_rate->service_code) ? $selected_rate->service_code : '';
                    $shipping_info['service'] = isset($selected_rate->service) ? $selected_rate->service : '';
                    // Add address info (if using geo-location)
                    if (!empty($shipping_info['origin_postalcode']) && $this->options->geolocate_origin){
                        $this->shipment_address = (array)get_option('woocommerce_cpwebservice_shipment_address', array());
                        if (count($this->shipment_address) > 1){
                            $sender_address_index = 0;
                            // find address index.
                            for($i=0;$i<count($this->shipment_address); $i++){
                                if ($this->shipment_address[$i]['postalcode'] == $shipping_info['origin_postalcode']) { $sender_address_index = $i;  }
                            }
                            $shipping_info['sender_address_index'] = $sender_address_index;
                            $shipping_info['sender_address'] = $this->shipment_address[$sender_address_index];
                        }
                    }
                    // Save with order.
                    $order = new WC_Order( $id ); 
                    $destination_country = isset($order) && is_object($order) ? $this->order_prop($order, 'shipping_country') : '';
                    $destination_postalcode = isset($order) && is_object($order) ? $this->order_prop($order, 'shipping_postcode') : '';
                    $packages = $shipping_info['packages'];
                    $limit = apply_filters('cpwebservice_auto_package_limit', 30);//limit 30 by default
                    foreach($packages as $package_index => $package){
                        $shipping = array('order_id'=>$id, 'package_index'=>$package_index, 'list_id'=>'', 'shipment'=> '');
                        $shipping['package'] = $package;
                        $shipping['sender_address_index']   = isset($shipping_info['sender_address_index']) ? $shipping_info['sender_address_index'] : 0;
                        $shipping['sender_address']         = isset($shipping_info['sender_address'])       ? $shipping_info['sender_address'] : '';
                        $shipping['origin_postalcode']      = isset($shipping_info['origin_postalcode'])    ? $shipping_info['origin_postalcode'] : '';
                        $shipping['rates']                  = !empty($package['rate']) ? $package['rate'] : (isset($shipping_info['rates']) ? $shipping_info['rates'] : array());
                        $shipping['service_code']           = isset($shipping_info['service_code'])        ? $shipping_info['service_code'] : '';
                        $shipping['service']                = isset($shipping_info['service'])             ? $shipping_info['service'] : '';
                        $shipping['destination_country']    = $destination_country;
                        $shipping['destination_postalcode'] = $destination_postalcode;
                        
                        $this->db->shipment_save($id, $shipping, $package_index);
                        
                        if ($package_index >= $limit){ 
                            break;
                        }
                    }
                } catch(Exception $ex){
                    global $wpdb;
                    // Provide message to admin user.
                    $msg = array('error'=>(!empty($wpdb->last_error) ? $wpdb->last_error . ' ' : '') . $ex->getMessage(), 'type'=>'order_processed');
                    set_transient( 'cpwebservice_db_msg', $msg, 1 * MINUTE_IN_SECONDS );
                }
			}
			// Clear shipping session data.
			$this->order_shipping_info_reset();
		}
	}
	
	
	/*
	 * Shipping rate data saved in session
	*/
	function order_shipping_info_reset(){
		if (WC()->session->cpwebservice_shipping_info!=null){
			unset( WC()->session->cpwebservice_shipping_info );
		}
		if (WC()->session->cpwebservice_shipping_selected_rate!=null){
		    unset( WC()->session->cpwebservice_shipping_selected_rate);
		}
	}
	
	public function add_shipping_details_box() {
        global $post_id;
		if ($this->shipments_exist($post_id) || $this->get_resource('shipments_implemented')){
		  add_meta_box( 'cpwebservice_shipping_details', __( 'Order Shipping Details', 'woocommerce-canadapost-webservice' ),  array(&$this,'display_shipping_view'), 'shop_order', 'normal', 'default' );
		}
	}
	
	public function display_shipping_view(){
        global $post_id;
        $order_id = $post_id;
		?>
		<div><img src="<?php echo plugins_url( $this->get_resource('method_logo_url') , dirname(__FILE__) ); ?>" /></div>
    <div id="cpwebservice_shipping_info" data-orderid="<?php echo esc_attr($order_id); ?>">
		<?php 
		// Shipping information. 
		$shipping_info = $this->get_shipping_info($order_id);
		?>
		<div id="cpwebservice_order_actions"><?php 
        if (!empty($shipping_info) && is_array($shipping_info)) {
	     
            if (!empty($shipping_info[0]) && isset($shipping_info[0]['rates']) && is_object($shipping_info[0]['rates'])) {
				// Get selected rate.
                $rates = $shipping_info[0]['rates'];
				?><p><strong><?php echo esc_html($this->get_resource('method_title')); ?> <?php echo esc_html(!empty($rates->service) ? $rates->service : '') ?></strong> 
				   <?php echo !empty($rates->expected_delivery) ? '<br />'.esc_html(( !empty($rates->guaranteed) ? __('Guaranteed', 'woocommerce-canadapost-webservice') . ' ' : '') .__('Delivered by', 'woocommerce-canadapost-webservice'). ' ' .$rates->expected_delivery) : ''  ?>
					<?php echo esc_html((!empty($rates->expected_delivery) && !empty($rates->expected_mailing_date)) ? __('if mailed by', 'woocommerce-canadapost-webservice').': ' . $rates->expected_mailing_date : '') ?> 
                  </p>
		<?php 
			}
			if (!empty($shipping_info[0]['sender_address'])){
                $sender_address = $shipping_info[0]['sender_address'];
			    ?><p><?php esc_html_e('Send From (Origin Address)', 'woocommerce-canadapost-webservice')?>: <br />
			   <strong><?php echo esc_html($sender_address['contact'])?></strong><?php if (!empty($sender_address['phone'])) { ?><br /><?php } ?>
				    <?php echo esc_html($sender_address['phone'])?><br />
					<?php echo esc_html($sender_address['address'])?><?php if (!empty($sender_address['address2'])) { ?><br /><?php } ?>
					<?php echo esc_html($sender_address['address2'])?><br />
					<?php echo esc_html($sender_address['city'])?>, <?php echo esc_html($sender_address['prov'])?> <?php echo esc_html($sender_address['postalcode'])?><br />
					<?php echo esc_html($this->display_country_name($sender_address['country']))?>
			    </p>
			    <?php 
			}
			if (!empty($shipping_info) && is_array($shipping_info)) {
				
			    $this->display_shipment_rows($shipping_info, $order_id);
				 
			} // end if
				
			
		} else {
			echo '<p>' . esc_html__('No shipment information saved with order', 'woocommerce-canadapost-webservice') . '.</p>'; 
		}
			?>
		</div>
	<?php if ($this->options->shipments_enabled && !empty($order_id)) : ?>
    <div style="margin:6px 0;">
    <?php $next_index = $this->get_next_package_index($shipping_info); ?>
    <a href="<?php echo wp_nonce_url( admin_url( 'admin-ajax.php?action=cpwebservice_create_shipment&order_id=' . $order_id . '&package_index=' . $next_index ), 'cpwebservice_create_shipment' ); ?>" class="button <?php if ($next_index == 0): ?>button-primary<?php endif;?> cpwebservice_iframe_modal_btn cpwebservice_createnew_btn" target="_blank">
    <?php esc_html_e('Create New Shipment', 'woocommerce-canadapost-webservice'); ?></a>
    <a href="#" onclick="return cpwebservice_order_refresh();" class="button button-secondary"><span class="dashicons dashicons-update" style="margin-top: 5px;"></span> Refresh</a>
    </div>						
    <?php endif; ?>
</div>
<?php 
	}
	
	public function display_shipment_rows($shipping_info, $order_id) {
	    ?>
	    <h4> <?php esc_html_e('Packages', 'woocommerce-canadapost-webservice') ?> (<?php echo esc_html(count($shipping_info)) ?>)
	    					<?php esc_html_e('to be shipped by', 'woocommerce-canadapost-webservice') ?> <?php echo esc_html($this->get_resource('method_title')) ?></h4>
	    					
	    	<table class="widefat">
	    	<thead>
	    		<tr>
	    			<th width="10%"></th>
	    			<th><?php esc_html_e('Package', 'woocommerce-canadapost-webservice'); ?> <?php esc_html_e('Dimensions', 'woocommerce-canadapost-webservice'); ?>, <?php esc_html_e('Shipping Weight', 'woocommerce-canadapost-webservice'); ?>, <?php esc_html_e('Volume/Cubic', 'woocommerce-canadapost-webservice'); ?></th>
	    			<th><?php esc_html_e('Service', 'woocommerce-canadapost-webservice'); ?></th>
	    			<th><?php esc_html_e('Products Packed', 'woocommerce-canadapost-webservice'); ?></th>
	    		</tr>
	    </thead>
	    <tbody>
	    <?php 
	    foreach($shipping_info as $shipping ){
                    $index = $shipping['package_index'];
                    $package = $shipping['package'];
                    $shipment = isset($shipping['shipment']) ? $shipping['shipment'] : array(); ?>
	    			<?php if (isset($package['length']) && isset($package['width']) && isset($package['height']) && isset($package['weight'])){ ?>
	    			<tr>
	    			<td class="cpwebservice_order_actions" nowrap="nowrap">
                    <?php if ($this->options->shipments_enabled) : ?>
	    			<?php if (!empty($shipment) && !empty($shipment['label'])) { ?>
	    			<a href="<?php echo wp_nonce_url( admin_url( 'admin-ajax.php?action=cpwebservice_create_shipment&order_id=' . $order_id . '&package_index=' . $index ), 'cpwebservice_create_shipment' ); ?>" class="button button-primary button-canadapost-print cpwebservice_iframe_modal_btn" target="_blank">
	    				<?php esc_html_e('Shipment Label', 'woocommerce-canadapost-webservice'); ?></a>
	    			<?php } else { ?>
	    			  <a href="<?php echo wp_nonce_url( admin_url( 'admin-ajax.php?action=cpwebservice_create_shipment&order_id=' . $order_id . '&package_index=' . $index ), 'cpwebservice_create_shipment' ); ?>" class="button button-primary cpwebservice_iframe_modal_btn" target="_blank"><?php esc_html_e('Create Shipment', 'woocommerce-canadapost-webservice'); ?></a>
	    			  <a href="<?php echo admin_url( 'admin-ajax.php?action=cpwebservice_shipment_remove&order_id=' . $order_id . '&package_index=' . $index ); ?>" class="button canadapost-btn-icon cpwebservice_shipment_remove" target="_blank" title="<?php esc_attr_e('Remove', 'woocommerce-canadapost-webservice'); ?>"><span class="dashicons dashicons-no"></span></a>
	    			<?php } // endif ?>
                    <?php else: ?>
                     <a href="<?php echo admin_url( 'admin-ajax.php?action=cpwebservice_package_remove&order_id=' . $order_id . '&package_index=' . $index ); ?>" class="button canadapost-btn-icon cpwebservice_package_remove" target="_blank" title="<?php esc_attr_e('Remove', 'woocommerce-canadapost-webservice'); ?>"><span class="dashicons dashicons-no"></span></a>
                    <?php endif; ?>
	    		    </td>
	    			<td><?php echo esc_html(cpwebservice_resources::display_unit($package['length'], $this->options->display_units) .' x '. cpwebservice_resources::display_unit($package['width'], $this->options->display_units) .' x ' . cpwebservice_resources::display_unit($package['height'], $this->options->display_units) . ' ' . $this->options->display_units )?> 
	    				<?php echo isset($package['box_name']) ? ' (' . esc_html__('Box Name', 'woocommerce-canadapost-webservice').': '.esc_html($package['box_name']).')' : ''?>
	    				<?php echo (isset($package['prepacked']) && $package['prepacked']=='yes') ? ' ('.esc_html__('Prepackaged', 'woocommerce-canadapost-webservice').')' : ''?> 
	    			<br /><?php echo isset($package['cubic']) ? esc_html(cpwebservice_resources::display_unit_cubed($package['cubic'], $this->options->display_units)) . ' '.esc_html($this->options->display_units).'<sup>3</sup>' : ''?>
	    			<br /><?php echo esc_html(cpwebservice_resources::display_weight($package['weight'], $this->options->display_weights) . ' ' . esc_html($this->options->display_weights))?> <?php echo isset($package['actual_weight']) ? esc_html('('.__('Actual', 'woocommerce-canadapost-webservice') .': '.cpwebservice_resources::display_weight($package['actual_weight'], $this->options->display_weights) . ' '.$this->options->display_weights.')') : '' ?> 
	    			</td>
	    			<td><?php
	    			$service_code = !empty($shipping['service_code']) ? $shipping['service_code'] : '';
                    $method_name = !empty($shipping['service']) ? $shipping['service'] : '';
	    			if (isset($shipment) && !empty($shipment['method_id'])){
	    			    $service_code = $shipment['method_id'];
	    			    $method_name = isset($shipment['method_name']) ? $shipment['method_name'] : '';
	    			}
	    			if (!empty($service_code) && !empty($shipping['rates']) && is_array($shipping['rates'])) {
	    						foreach($shipping['rates'] as $itemrate){
	    							if (isset($itemrate->service_code) && $itemrate->service_code == $service_code){
	    							?>
	    							<p> <strong><?php echo esc_html($this->get_resource('method_title')); ?> <?php echo esc_html(!empty($itemrate->service) ? $itemrate->service : $method_name) ?></strong> 
	    							<?php if(!empty($itemrate->price)): ?><br /><?php esc_html_e('My Cost', 'woocommerce-canadapost-webservice') ?>: $<?php echo esc_html(!empty($itemrate->price) ? number_format(floatval($itemrate->price),2,'.','') : '') ?>
	    							<?php endif; ?> <?php echo !empty($itemrate->expected_delivery) ? '<br />'.esc_html(( !empty($itemrate->guaranteed) ? __('Guaranteed', 'woocommerce-canadapost-webservice') . ' ' : '') .__('Delivered by', 'woocommerce-canadapost-webservice'). ' ' .$itemrate->expected_delivery) : ''  ?>
	    							</p>
	    							<?php
	    							}
	    						}// endforeach
	    
	    				} // endif
	    				else if (!empty($service_code) && !empty($method_name)){ ?>
	    			     <p><strong><?php echo esc_html($this->get_resource('method_title')); ?> <?php echo esc_html($method_name); ?></strong> </p>	    
	    		    <?php }
	    			?></td>
	    			<td><?php if (!empty($package['products']) && is_array($package['products'])) { ?>
	    				<?php 
	    				$product_reference = $this->get_product_array($package['products']);
	    				// Display
	    				$product_groups = $this->group_products($package['products']);
	    				foreach($product_groups as $item){ 
	    					if (isset($item['item_id']) && isset($product_reference[$item['item_id']])){
	    					$p = $product_reference[$item['item_id']];
	    					?>
	    					&bull; <?php echo esc_html($item['count']); ?>x <?php $this->display_product_variation($p); ?> <a href="<?php echo esc_attr($p->get_permalink()); ?>"><?php echo esc_html($p->get_title()) ?></a> <?php echo esc_html($p->get_sku()); ?> (<?php echo esc_html(function_exists('wc_format_dimensions') ? wc_format_dimensions($p->get_dimensions(false)) : $p->get_dimensions()); ?> &nbsp; <?php echo esc_html($p->get_weight() . ' ' . get_option('woocommerce_weight_unit')); ?>) <br />
	    					<?php 
	    					}// endif
	    				}// end foreach
	    				?>
	    				<?php } // endif ?>
	    			</td>
	    			</tr>
	    	<?php } // endif ?>
	    <?php } // end foreach ?> 
	    </tbody>
        </table>
        <?php 
	}
	
	
	public function display_product_variation($product) {
	    if ($product->is_type('variation')){
	        $variation = $product->get_variation_attributes();
	        echo esc_html(implode(',', $variation));
	    }
	}
	
	public function order_actions_ajax() {
	    // Displays Order actions rows by ajax.
	    if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'cpwebservice_order_actions' ) )
	        return;
	    
	    if( !current_user_can( 'manage_woocommerce_orders' ) && !current_user_can( 'edit_shop_orders' ) ) {
	        wp_die( esc_attr__( 'You do not have sufficient permissions to access this page.' ) );
	    }
	    
	    $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
	    
	    // Get shipping Info 
	    $shipping_info = $this->get_shipping_info($order_id);
	    if (!empty($shipping_info)){
	        $this->display_shipment_rows($shipping_info, $order_id);
	    } else {
	        echo '<p>' . esc_html__('No information available', 'woocommerce-canadapost-webservice') . '</p>';
	    }
	    $next_index = $this->get_next_package_index($shipping_info);
	    ?>
	    <div class="cpwebservice_createnew" data-url="<?php echo wp_nonce_url( admin_url( 'admin-ajax.php?action=cpwebservice_create_shipment&order_id=' . $order_id . '&package_index=' . $next_index ), 'cpwebservice_create_shipment' ); ?>"></div><?php
	    exit; // ajax return.
	}
	
	// Get item_ids into an easy-to-use array.
	public function get_product_ids($products){
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
	
	// Looks up products in $package['products']
	public function get_product_array($products){
		// Begin Do Lookup
		$ids = $this->get_product_ids($products);
		$product_reference = array();
		// This method needs documentation on 'include'. Using get_posts.
// 		if (function_exists('wc_get_products')){ // WC 3.x
// 		    $items = wc_get_products(array('include' => $ids, 'limit'=> 0, 'type' => array('simple','variation','grouped','variable')));
// 		    foreach($items as $item){
// 		        $product_reference[$item->get_id()] = $item;
// 		    } // endforeach
// 		} else {
		    // Lookup using posts.
    		$items = get_posts(array('post_type' => array('product','product_variation'), 'post__in' => $ids ));
    		foreach($items as $item){ 
    			$p = wc_get_product($item);
    			$product_reference[$item->ID] = $p;
    		} // endforeach
    		wp_reset_postdata();
    		// End Do Lookup.
	//	}
		
		return $product_reference;
	}
	
	// Group (and count) Products by ID
	public function group_products($products){
		$group = array();
		foreach($products as $level){
			foreach($level as $p){
				if (isset($p['item_id'])){
					$id = $p['item_id'];
					if (isset($group[$id])) {
						$group[$id]['count'] += 1;
					} else {
						$group[$id] = $p;
						$group[$id]['count'] = 1;
					}
				}
			}
		}
		return $group;
	}

    public function get_next_package_index($shipping_info){
        // Next package_index
        return !empty($shipping_info) ? ($shipping_info[count($shipping_info)-1]['package_index'] + 1) : 0;
    }

    public function admin_notice_dbmsg() {
        $message =  $this->get_resource('method_title') . __( ' Database issue: ', 'woocommerce-canadapost-webservice' );
        if (!empty($this->dbmsg) && $this->dbmsg['type'] =='db_upgrade'){
            $message .= __( ' The plugin had problems creating the database tables as needed.', 'woocommerce-canadapost-webservice' );
            if (stristr($this->dbmsg['error'], 'permission')){ 
                $message .= __( ' The database user needs permission to create the table.', 'woocommerce-canadapost-webservice' );
            }
        }
        printf( '<div class="%s"><p>%s <small><br />(%s)</small></p></div>', esc_attr( 'notice notice-error is-dismissible cpwebservice_admin_notice' ), esc_html( $message ), esc_html( !empty($this->dbmsg) ? $this->dbmsg['error'] : '' )); 
    }
    public function admin_notice_dbmsg_clear(){
        delete_transient('cpwebservice_db_msg');
        return;
    }

    /*
	 * Ajax request for draft shipment removal. 
	 */
	public function package_remove(){
	    if( !is_admin() ) {
	        wp_die( esc_attr__( 'You do not have sufficient permissions to access this page.' ) );
	    }
	    // Check the user privileges
	    if( !current_user_can( 'manage_woocommerce_orders' ) && !current_user_can( 'edit_shop_orders' ) ) {
	        wp_die( esc_attr__( 'You do not have sufficient permissions to access this page.' ) );
	    }
	    // Nonce.
	    if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'cpwebservice_package_remove' ) )
	        wp_die( esc_attr__( 'You do not have sufficient permissions to access this page.' ) );
	
	    // Parameters
	    $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
	    $package_index = isset($_GET['package_index']) ? intval($_GET['package_index']) : 0;
	    
        if (!empty($order_id) && $package_index >= 0){
            // Get Shipment info
            $shipping_info = $this->db->shipment_get($order_id, $package_index);

            // Validate whether this shipment is just a draft (no label or refund) and can be deleted.
            if (!isset($shipping_info['label']) && !isset($shipping_info['refund'])){
                // Delete shipment data.
                // Remove this record.
                $this->db->shipment_remove($order_id, $package_index);
                esc_html_e('Success! Draft shipment was deleted.', 'woocommerce-canadapost-webservice');
            } else {
                // Invalid. Label has already been created.
                esc_html_e('Error: Cannot remove. Shipment label has already been created.', 'woocommerce-canadapost-webservice');
            }
        }
	    exit; // ajax response.
	}
    public function display_country_name($country_code){
        if (isset(WC()->countries->countries[ $country_code ])){
            return WC()->countries->countries[ $country_code ];
        }
        return $country_code;
    }
    public function order_prop($order, $property){
	    if (method_exists($order, 'get_'.$property)){
	        return $order->{'get_'.$property}();
	    } else {
	        return $order->{$property};
	    }
	}
	public function init_shippingmethod() 
	{
	    // Load up woocommerce shipping stack.
	    do_action('woocommerce_shipping_init');
	    // Add shipping method class
	    $this->shippingmethod = new woocommerce_cpwebservice();
	}

    private function shipments_legacy_orderdata($order_id, $shipping_info){
        $order = null;
        $destination_country = '';
        $destination_postalcode = '';
        $method_name = '';
        $service_code = '';
        $order_shipping = null;
        for ($i=0; $i < count($shipping_info); $i++) {
            if (empty($shipping_info[$i]['service_code']) || empty($shipping_info[$i]['destination_country']) || empty($shipping_info[$i]['destination_postalcode'])){
                if ($order == null) { 
                    $order = new WC_Order( $order_id ); 
                    if (!empty($order) && is_object($order)) {
                        $destination_country = $this->order_prop($order, 'shipping_country');
                        $destination_postalcode = $this->order_prop($order, 'shipping_postcode');
                        $order_shipping = $order->get_shipping_methods();
                    }
                }
                if (empty($shipping_info[$i]['destination_country'] && !empty($destination_country))){
                    $shipping_info[$i]['destination_country'] = $destination_country;
                }
                if (empty($shipping_info[$i]['destination_postalcode'] && !empty($destination_postalcode))){
                    $shipping_info[$i]['destination_postalcode'] = $destination_postalcode;
                }
                if (empty($shipping_info[$i]['service_code'] && !empty($order_shipping))){
                    // Get from method.
                    if (empty($method_name)){
                        foreach($order_shipping as $ship){
                            if (!empty($ship->get_method_id()) && $ship->get_method_id() == 'woocommerce_cpwebservice'){
                                $method_name = !empty($ship->get_method_title()) ? $ship->get_method_title() : $ship->get_name();
                                break;
                            }
                        }
                        if (!empty($method_name)){
                            // Convert to method_id
                            if ($this->shippingmethod == null){ $this->init_shippingmethod(); }
                            $service_code = $this->shippingmethod->get_service_code($method_name);
                        }
                    }
                    if (!empty($service_code)){
                        $shipping_info[$i]['service_code'] = $service_code;
                        $shipping_info[$i]['service'] = $method_name;
                    }
                }
                if (!empty($shipping_info[$i]['service_code']) && empty($shipping_info[$i]['service'])){
                    if ($this->shippingmethod == null){ $this->init_shippingmethod(); }
                    $shipping_info[$i]['service'] = $this->shippingmethod->get_service_label($shipping_info[$i]['service_code']);
                }
                if (empty($shipping_info[$i]['origin_postalcode']) && isset($shipping_info[$i]['sender_address_index'])){
                    if ($this->shippingmethod == null){ $this->init_shippingmethod(); }
                    if (isset($this->shippingmethod->shipment_address) && isset($this->shippingmethod->shipment_address[$shipping_info[$i]['sender_address_index']])){
                        $shipping_info[$i]['origin_postalcode'] = $this->shippingmethod->shipment_address[$shipping_info[$i]['sender_address_index']]['postalcode'];
                    }
                }
            } 
        }  //end for
        return $shipping_info;
    }

    public function migrate_shipping_legacy_data(){
        if( !is_admin() &&
			// Check the user privileges
			!current_user_can( 'manage_woocommerce_orders' ) && !current_user_can( 'edit_shop_orders' ) &&
			// Check the action
			(empty( $_GET['action'] ) || !check_admin_referer( $_GET['action'] ) )
		) {
			wp_die( esc_attr__( 'You do not have sufficient permissions to access this page.' ) );
		}
		
		// Nonce.
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'cpwebservice_migrate_shipping_legacy_data' ) )
            return;

       $starttime = microtime(true);
       $endinterval = 25;
       $continue = false;
       $updated = isset($_GET['continue']) ? intval($_GET['continue']) : 0;
        // Moves any orders with legacy data.
        $order_ids = $this->db->shipments_get_all_legacy_order_ids();
        if (!empty($order_ids) && is_array($order_ids)){
            foreach ($order_ids as $id) {
                $_ = $this->get_shipping_info_legacy($id); // Legacy
                $updated++;
                if (microtime(true) - $starttime > $endinterval){
                    $continue = true;
                    break;
                }
            }
        } 
        if ($continue){
            echo esc_html('Continue. (' . $updated . ' updated). ' . __('Update will complete soon.', 'woocommerce-canadapost-webservice'));
        } else {
            // Load up woocommerce shipping stack.
            do_action('woocommerce_shipping_init');
            // Shipping method class
            $shippingmethod = new woocommerce_cpwebservice();
            // Turn off Legacy tracking
            $shippingmethod->options->legacyshipping = false;
            update_option('woocommerce_cpwebservice', $shippingmethod->options);
            echo esc_html(__('Complete.', 'woocommerce-canadapost-webservice') .' '. ($updated > 0 ? $updated . ' updated. ' : ''));
        }
        
		exit;
    }
}