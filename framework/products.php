<?php
/*
 Product Options class
woocommerce_cpwebservice_products.php

Copyright (c) 2013-2022 Jamez Picard

*/
abstract class cpwebservice_products
{
	/**
	 * __construct function.
	 *
	 * @access public
	 * @return woocommerce_cpwebservice_products
	 */
	function __construct() {
		$this->init();
	}
	
	protected $origin_states;
	protected $origin_countries;

    protected $options;

	/**
	 * init function.
	 *
	 * @access public
	 * @return void
	 */
	function init() {
		$default_options = (object) array('enabled'=>'no', 'title'=>'', 'api_user'=>'', 'api_key'=>'','account'=>'','contractid'=>'','source_postalcode'=>'','mode'=>'live', 'geolocate_limit'=>false,
						   'delivery'=>'', 'delivery_guarantee'=>false, 'margin'=>'', 'margin_value'=>'', 'packageweight'=>floatval('0.02'), 'boxes_enable'=> false, 'boxes_switch'=>true, 'lettermail_enable'=> false, 'rules_enable'=>false, 'volumetric_weight'=>true, 'product_shipping_options' => true,
						   'shipping_tracking'=> true, 'email_tracking'=> true, 'log_enable'=>false,'lettermail_limits'=>false,'lettermail_maxlength'=>'','lettermail_maxwidth'=>'','lettermail_maxheight'=>'','lettermail_override_weight'=>false,'lettermail_packageweight'=>'', 'tracking_icons'=> true, 'display_required_notice'=>true,'shipment_hscodes'=>false);
		$this->options		= get_option('woocommerce_cpwebservice', $default_options);
		$this->options		= (object) array_merge((array) $default_options, (array) $this->options); // ensure all keys exist, as defined in default_options.
		$this->enabled		= ($this->options->product_shipping_options || $this->options->shipment_hscodes) && ( !empty($this->options->api_user) && !empty($this->options->api_key) );

		if ($this->enabled) {
			// Actions
			add_action('woocommerce_product_options_shipping',  array(&$this, 'add_product_shipping_box') );
			add_action( 'save_post', array(&$this, 'save_product_shipping'), 10, 2 );
			add_action( 'woocommerce_product_after_variable_attributes',  array(&$this, 'add_product_shipping_box_variation'), 10, 3 );
			add_action( 'woocommerce_save_product_variation',  array(&$this, 'save_product_variation'), 10, 2 );
		}

	}
	
	/*
	 * Return resources
	 */
	abstract function get_resource($id);

	

	/* Adds a box to the product Edit screen in the Shipping Tab. */
	public function add_product_shipping_box() {
		global $post;
		$product_shipping = get_post_meta( $post->ID, '_cpwebservice_product_shipping', true );
		?>
		<h4 class="cpwebservice_option_title" style="padding: 20px 20px 5px 12px; border-top: 1px solid #eee;margin:0;"><?php echo esc_html($this->get_resource('method_title')); ?></h4>
		<p class="form-field cpwebservice_product_shipping_field">
		<label for="cpwebservice_product_shipping"><?php echo esc_html($this->get_resource('method_title')); ?>: <?php esc_html_e('Package Separately (Pre-packaged)', 'woocommerce-canadapost-webservice' ); ?>:</label> 
		<input name="cpwebservice_product_shipping" type="checkbox" value="1" <?php checked($product_shipping, 'yes'); ?> /> 
		<span class="woocommerce-help-tip" data-tip="<?php esc_attr_e( 'If this is selected, this item will not be placed in any box but assumed already packed and ready to ship when calculating rates with', 'woocommerce-canadapost-webservice' ); ?> <?php esc_attr_e($this->get_resource('method_title')); ?>"></span></p>
		<?php
		if($this->options->geolocate_limit){
		$shipment_addresses = get_option('woocommerce_cpwebservice_shipment_address');
		// Only displays form if more than 1 shipping address/warehouse is defined.
		if (!empty($shipment_addresses) && is_array($shipment_addresses) && count($shipment_addresses) > 1){
		  $product_warehouse = get_post_meta( $post->ID, '_cpwebservice_product_warehouse', true );
		?>
		<p class="form-field cpwebservice_product_warehouse_field">
		<label for="cpwebservice_product_warehouse"><?php echo esc_html($this->get_resource('method_title')); ?>: <?php esc_html_e('Sender Address/Warehouse', 'woocommerce-canadapost-webservice' ); ?>:</label>
		<select name="cpwebservice_product_warehouse[]" class="form-control chosen_select" style="width:100%" data-placeholder="<?php esc_attr_e('Any Sender Address/Warehouse' , 'woocommerce-canadapost-webservice' )?>" multiple>
				    <?php foreach ( $shipment_addresses as $id=>$address ) : ?>
    							<option value="<?php echo esc_attr( $id.'_'.$address['postalcode'] ); ?>" <?php selected( !empty($product_warehouse) && in_array($id.'_'.$address['postalcode'], $product_warehouse) ); ?>><?php echo esc_attr( $address['contact'] . ' ' . $address['postalcode'] ); ?></option>
    				<?php endforeach; ?>
				    </select>
				    <span class="woocommerce-help-tip" data-tip="<?php esc_attr_e( 'Limit this item to only be shipped from selected Address/Warehouses. Default is allow from all.', 'woocommerce-canadapost-webservice' ); ?>"></span> 
		</p>
		<?php } // endif 
		} // endif
		if ($this->options->shipment_hscodes){
		     $origin_country = $this->get_resource('shipment_country'); 
		     if (empty($this->origin_states)) { $this->origin_states =  WC()->countries->get_states( $origin_country ); }
		     if (empty($this->origin_countries)){ $this->origin_countries = WC()->countries->countries; }
		     
		     $product_customs = get_post_meta( $post->ID, '_cpwebservice_product_customs', true );
		     $default = array('hscodes'=>'','hscodes_descr'=>'','origin_country'=>'','origin_prov'=>'');
		     $product_customs = (object) array_merge($default, !empty($product_customs) ? $product_customs : array());
		    ?>
		  <p class="form-field cpwebservice_product_hscodes_field">
		  <label for="cpwebservice_product_hscodes"><?php esc_html_e('Customs HS Code', 'woocommerce-canadapost-webservice' ); ?>:  <small><a href="<?php echo esc_attr($this->get_resource('hscode_search_url')); ?>" target="_blank">(<?php esc_html_e('HS Code Search','woocommerce-canadapost-webservice')?> )</a></small></label>
		  <span class="wrap">
		  <input name="cpwebservice_product_hscodes" type="input" class="input-text" size="16" value="<?php echo esc_attr($product_customs->hscodes); ?>" />
		  </span> 
			<span class="woocommerce-help-tip" data-tip="<?php esc_attr_e( 'This value will be populated for this product on the Shipment Label Form for ', 'woocommerce-canadapost-webservice' ); ?> <?php esc_attr_e($this->get_resource('method_title')); ?>"></span></p>
			<p class="form-field">
		  <label for="cpwebservice_product_hscodes_descr"><?php esc_html_e('Customs', 'woocommerce-canadapost-webservice' ); ?> <?php esc_html_e('Product', 'woocommerce-canadapost-webservice' ); ?> <?php _e('Description', 'woocommerce-canadapost-webservice'); ?>: </label>
		  <span class="wrap">
		  <input name="cpwebservice_product_hscodes_descr" type="input" class="input-text" size="30" value="<?php echo esc_attr($product_customs->hscodes_descr); ?>" placeholder="<?php echo esc_attr(get_the_title($post)); ?>" />
		  </span> 
		  <span class="woocommerce-help-tip" data-tip="<?php esc_attr_e( 'This value will be populated for this product on the Shipment Label Form for ', 'woocommerce-canadapost-webservice' ); ?> <?php esc_attr_e($this->get_resource('method_title')); ?>"></span></p>
		    <p class="form-field">
		        <label class="control-label"><?php esc_html_e('Country of Origin', 'woocommerce-canadapost-webservice') ?>: </label>
		          <select name="cpwebservice_product_origin_country" class="canadapost-origin-country short" data-origincountry="<?php echo esc_attr($origin_country); ?>">
				    <option value="" <?php selected( '', esc_attr( $product_customs->origin_country ) ); ?>></option>
				     <?php if ($this->origin_countries): ?>
					<?php foreach ( $this->origin_countries as $option_key => $option_value ) : ?>
					 <option value="<?php echo esc_attr( $option_key ); ?>" <?php selected( $option_key, esc_attr( $product_customs->origin_country ) ); ?>><?php echo esc_attr( $option_value ); ?></option>
					<?php endforeach; ?>
					<?php endif; ?>
					</select>
		    </p>
		    <p class="form-field canadapost-origin-prov" <?php if($product_customs->origin_country != $origin_country){ ?> style="display:none"<?php }?>>
		         <label><?php esc_html_e('Province of Origin', 'woocommerce-canadapost-webservice') ?>:  </label>
		         <select name="cpwebservice_product_origin_prov" class="canadapost-origin-prov-control">
				    <option value="" <?php selected( '', esc_attr( $product_customs->origin_prov ) ); ?>></option>
					<?php foreach ( (array) $this->origin_states as $option_key => $option_value ) : ?>
					 <option value="<?php echo esc_attr( $option_key ); ?>" <?php selected( $option_key, esc_attr( $product_customs->origin_prov ) ); ?>><?php echo esc_attr( $option_value ); ?></option>
					<?php endforeach; ?>
				 </select>
		    </p>
		    <script type="text/javascript">
		    jQuery(function($){
			    $('.canadapost-origin-country').on('change',function(){ var country=$(this);  country.parent().next('.canadapost-origin-prov').toggle(country.val()==country.data('origincountry'));  });
		    });
		    </script>
		    <?php 
		}
	}
	
	// do_action ('save_post')
	public function save_product_shipping($post_id, $post){
		// If this isn't a 'product', don't update it.
		if ( 'product' != $post->post_type ) {
			return;
		}
		// Let the backend only access the page
		if( !is_admin() ) {
			wp_die( esc_attr__( 'You do not have sufficient permissions to access this page.' ) );
		}
		// Check the user privileges
		if( !current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		// Pre-packaged
		$product_shipping     = isset($_POST['cpwebservice_product_shipping'] ) ? 'yes' : 'no';
		// Update post meta
		update_post_meta( $post_id, '_cpwebservice_product_shipping', wc_clean( $product_shipping ) );
		
		if($this->options->geolocate_limit){
    		// Sender Address/Warehouse
    		$shipment_addresses = get_option('woocommerce_cpwebservice_shipment_address');
    		if (!empty($shipment_addresses) && is_array($shipment_addresses) && count($shipment_addresses) > 1){
    		    $warehouses = array();
    		    if (isset($_POST['cpwebservice_product_warehouse'] ) && is_array($_POST['cpwebservice_product_warehouse'])) {
        		    foreach($_POST['cpwebservice_product_warehouse'] as $w){
        		        $warehouses[] = wc_clean($w);
        		    }
    		    }
        		// Update post meta
        		update_post_meta( $post_id, '_cpwebservice_product_warehouse', $warehouses );
    		}
		}
		if ($this->options->shipment_hscodes){
		    $product_customs = array('hscodes'=>'','origin_country'=>'','origin_prov'=>'');
		    $origin_country = $this->get_resource('shipment_country');
				$product_customs['hscodes'] = !empty($_POST['cpwebservice_product_hscodes'] ) ? wc_clean($_POST['cpwebservice_product_hscodes']) : '';
				$product_customs['hscodes_descr'] = !empty($_POST['cpwebservice_product_hscodes_descr'] ) ? $this->truncate(wc_clean($_POST['cpwebservice_product_hscodes_descr']),45) : '';
		    $product_customs['origin_country'] = !empty($_POST['cpwebservice_product_origin_country'] ) ? wc_clean($_POST['cpwebservice_product_origin_country']) : '';
		    $product_customs['origin_prov'] = $product_customs['origin_country'] == $origin_country && !empty($_POST['cpwebservice_product_origin_prov'] ) ? wc_clean($_POST['cpwebservice_product_origin_prov']) : '';
		    		    
		    update_post_meta( $post_id, '_cpwebservice_product_customs', $product_customs );
		}
	}
	
	// do_action('woocommerce_product_after_variable_attributes',$loop, $variation_data, $variation);
	public function add_product_shipping_box_variation($loop, $variation_data, $variation) {
	    ?>
	    <tr>
			<td class="hide_if_variation_virtual">
			<div class="options">
		<?php $product_shipping = get_post_meta( $variation->ID, '_cpwebservice_product_shipping', true ); ?>
			<h4 class="form-row form-row-full cpwebservice_variation_option_title"><?php echo esc_html($this->get_resource('method_title')); ?></h4>
			<p class="form-field">
				<label><?php echo esc_html($this->get_resource('method_title')); ?>: <?php esc_html_e('Package Separately (Pre-packaged)', 'woocommerce-canadapost-webservice' )?> <input name="variable_cpwebservice_product_shipping[<?php echo esc_attr($loop) ?>]" type="checkbox" class="checkbox" value="1" <?php checked($product_shipping, 'yes'); ?> /> <span class="woocommerce-help-tip" data-tip="<?php esc_attr_e( 'If this is selected, this item will not be placed in any box but assumed already packed and ready to ship when calculating rates with', 'woocommerce' ); ?> <?php esc_attr_e($this->get_resource('method_title')); ?>"></span></label>
			</p>
		<?php
		if($this->options->geolocate_limit){
		$shipment_addresses = get_option('woocommerce_cpwebservice_shipment_address');
		// Only displays form if more than 1 shipping address/warehouse is defined.
		if (!empty($shipment_addresses) && is_array($shipment_addresses) && count($shipment_addresses) > 1){
		  $product_warehouse = get_post_meta( $variation->ID, '_cpwebservice_product_warehouse', true );
		?>
		<p class="form-row">
			      <label for="variable_cpwebservice_product_warehouse"><?php echo esc_html($this->get_resource('method_title')); ?>: <?php esc_html_e('Sender Address/Warehouse', 'woocommerce-canadapost-webservice' ); ?>:  <span class="woocommerce-help-tip" data-tip="<?php esc_attr_e( 'Limit this item to only be shipped from selected Address/Warehouses. Default is allow from all.', 'woocommerce-canadapost-webservice' ); ?> <?php esc_attr_e($this->get_resource('method_title')); ?>"></span> </label>
			      <select name="variable_cpwebservice_product_warehouse[<?php echo esc_attr($loop) ?>][]" class="form-control chosen_select" style="width:100%" data-placeholder="<?php esc_attr_e('Any Sender Address/Warehouse' , 'woocommerce-canadapost-webservice' )?>" multiple>
					    <?php foreach ( $shipment_addresses as $id=>$address ) : ?>
	    							<option value="<?php echo esc_attr( $id.'_'.$address['postalcode'] ); ?>" <?php selected( !empty($product_warehouse) && in_array($id.'_'.$address['postalcode'], $product_warehouse) ); ?>><?php echo esc_attr( $address['contact'] . ' ' . $address['postalcode'] ); ?></option>
	    				<?php endforeach; ?>
					    </select>
		</p>
		<?php } //endif 
		    }// endif ?>
		<?php 
		 if ($this->options->shipment_hscodes){
		     $origin_country = $this->get_resource('shipment_country'); 
		     if (empty($this->origin_states)) { $this->origin_states =  WC()->countries->get_states( $origin_country ); }
		     if (empty($this->origin_countries)){ $this->origin_countries = WC()->countries->countries; }
		     
		     $product_customs = get_post_meta( $variation->ID, '_cpwebservice_product_customs', true );
		     $default = array('hscodes'=>'','origin_country'=>'','origin_prov'=>'');
		     $product_customs = (object) array_merge($default, !empty($product_customs) ? $product_customs : array());
		     
		    ?>
		   <p class="form-field">
		 		  <label><?php echo esc_html($this->get_resource('method_title')); ?>: <?php esc_html_e('Customs HS Code', 'woocommerce-canadapost-webservice' ); ?>:</label>  <small><a href="<?php echo esc_attr($this->get_resource('hscode_search_url')); ?>" target="_blank">(<?php esc_html_e('HS Code Search','woocommerce-canadapost-webservice')?>)</a></small>
				<input name="variable_cpwebservice_product_hscodes[<?php echo esc_attr($loop) ?>]" type="input" value="<?php echo esc_attr($product_customs->hscodes); ?>" /> 
				<span class="woocommerce-help-tip" data-tip="<?php esc_attr_e( 'This value will be populated for this product on the Shipment label form for ', 'woocommerce-canadapost-webservice' ); ?> <?php esc_attr_e($this->get_resource('method_title')); ?>"></span>
			</p>
			<p class="form-field">
		  <label><?php esc_html_e('Customs', 'woocommerce-canadapost-webservice' ); ?> <?php esc_html_e('Product', 'woocommerce-canadapost-webservice' ); ?> <?php esc_html_e('Description', 'woocommerce-canadapost-webservice'); ?>: </label>
		  <input name="variable_cpwebservice_product_hscodes_descr[<?php echo esc_attr($loop) ?>]" type="input" class="input-text" size="30" value="<?php echo esc_attr($product_customs->hscodes_descr); ?>" placeholder="<?php echo esc_attr(method_exists($variation,'get_title') ? $variation->get_title() : get_the_title($variation)); ?>" />
		  <span class="woocommerce-help-tip" data-tip="<?php esc_attr_e( 'This value will be populated for this product on the Shipment Label Form for ', 'woocommerce-canadapost-webservice' ); ?> <?php esc_attr_e($this->get_resource('method_title')); ?>"></span></p>
          <div class="form-row">
		        <label class="control-label"><?php esc_html_e('Country of Origin', 'woocommerce-canadapost-webservice') ?>: </label><br />
		          <select name="variable_cpwebservice_product_origin_country[<?php echo esc_attr($loop) ?>]" class="canadapost-origin-country short" data-origincountry="<?php echo esc_attr($origin_country); ?>">
				    <option value="" <?php selected( '', esc_attr( $product_customs->origin_country ) ); ?>></option>
				     <?php if ($this->origin_countries): ?>
					<?php foreach ( $this->origin_countries as $option_key => $option_value ) : ?>
					 <option value="<?php echo esc_attr( $option_key ); ?>" <?php selected( $option_key, esc_attr( $product_customs->origin_country ) ); ?>><?php echo esc_attr( $option_value ); ?></option>
					<?php endforeach; ?>
					<?php endif; ?>
					</select>
		    </div>
		    <div class="canadapost-origin-prov"<?php if($product_customs->origin_country!=$origin_country){ ?> style="display:none"<?php }?>>
		         <label><?php esc_html_e('Province of Origin', 'woocommerce-canadapost-webservice') ?>:  </label><br />
		         <select name="variable_cpwebservice_product_origin_prov[<?php echo esc_attr($loop) ?>]" class="canadapost-origin-prov-control">
				    <option value="" <?php selected( '', esc_attr( $product_customs->origin_prov ) ); ?>></option>
					<?php foreach ( (array) $this->origin_states as $option_key => $option_value ) : ?>
					 <option value="<?php echo esc_attr( $option_key ); ?>" <?php selected( $option_key, esc_attr( $product_customs->origin_prov ) ); ?>><?php echo esc_attr( $option_value ); ?></option>
					<?php endforeach; ?>
				 </select>
		    </div>
		    <br />
		    <script type="text/javascript">
		    jQuery(function($){
			    $('.canadapost-origin-country').on('change',function(){ var country=$(this);  country.parent().next('.canadapost-origin-prov').toggle(country.val()==country.data('origincountry'));  });
		    });
		    </script>
		    <?php 
		} ?>
		</div>
		</td>
			<td class="hide_if_variation_virtual"></td>
		</tr>
		<?php
	}

	//do_action( 'woocommerce_save_product_variation', $variation_id, $i );
	// called within a for($i)
	public function save_product_variation($variation_id, $i) {
		$variable_cpwebservice_product_shipping = isset( $_POST['variable_cpwebservice_product_shipping'] ) ? $_POST['variable_cpwebservice_product_shipping'] : array();
		$product_shipping     = isset( $variable_cpwebservice_product_shipping[ $i ] ) ? 'yes' : 'no';
		
		// Update variation
		update_post_meta( $variation_id, '_cpwebservice_product_shipping', wc_clean( $product_shipping ) );
		
		if($this->options->geolocate_limit){
    		// Sender Address/Warehouse
    		$shipment_addresses = get_option('woocommerce_cpwebservice_shipment_address');
    		if (!empty($shipment_addresses) && is_array($shipment_addresses) && count($shipment_addresses) > 1){
    		    $warehouses = array();
    		    if (isset($_POST['variable_cpwebservice_product_warehouse'] ) &&  is_array($_POST['variable_cpwebservice_product_warehouse']) && isset($_POST['variable_cpwebservice_product_warehouse'][$i])){
        		    foreach($_POST['variable_cpwebservice_product_warehouse'][$i] as $w){
        		        $warehouses[] = wc_clean($w);
        		    }
    		    }
    		    // Update post meta
    		    update_post_meta( $variation_id, '_cpwebservice_product_warehouse', $warehouses );
    		}
		}
		if ($this->options->shipment_hscodes){
		    $product_customs = array('hscodes'=>'','hscodes_descr'=>'','origin_country'=>'','origin_prov'=>'');
		    
		    $origin_country = $this->get_resource('shipment_country');
				$product_customs['hscodes'] = isset( $_POST['variable_cpwebservice_product_hscodes'] ) && !empty( $_POST['variable_cpwebservice_product_hscodes'][ $i ] ) ? wc_clean($_POST['variable_cpwebservice_product_hscodes'][ $i ]) : '';
				$product_customs['hscodes_descr'] = isset( $_POST['variable_cpwebservice_product_hscodes_descr'] ) && !empty( $_POST['variable_cpwebservice_product_hscodes_descr'][ $i ] ) ? $this->truncate(wc_clean($_POST['variable_cpwebservice_product_hscodes_descr'][ $i ]), 45) : '';
		    $product_customs['origin_country'] = isset( $_POST['variable_cpwebservice_product_origin_country'] ) && !empty( $_POST['variable_cpwebservice_product_origin_country'][ $i ] ) ? wc_clean($_POST['variable_cpwebservice_product_origin_country'][ $i ]) : '';
		    $product_customs['origin_prov'] = $product_customs['origin_country'] == $origin_country && isset( $_POST['variable_cpwebservice_product_origin_prov'] ) && !empty( $_POST['variable_cpwebservice_product_origin_prov'][ $i ] ) ? wc_clean($_POST['variable_cpwebservice_product_origin_prov'][ $i ]) : '';
		    
		    // Update post meta hs codes
		    update_post_meta( $variation_id, '_cpwebservice_product_customs', $product_customs );
		}
	}
	
	public function truncate($string,$length=100,$append="") {
		$string = trim($string);

		if(strlen($string) > $length) {
				$string = wordwrap($string, $length);
				$string = explode("\n", $string, 2);
				$string = $string[0] . $append;
		}

		return $string;
  }
	
}