<?php
/*
 Main Tracking Class
woocommerce_cpwebservice_tracking.php

Copyright (c) 2013-2022 Jamez Picard

*/
abstract class cpwebservice_tracking
{

	/**
	 * __construct function.
	 *
	 * @access public
	 * @return woocommerce_cpwebservice_tracking
	 */
	function __construct() {
		$this->init();
	}

	public $options;
	public $enabled;
	public $log;
	protected $pins;
	protected $cached;
    protected $tracking_email;

	/**
	 * init function.
	 *
	 * @access public
	 * @return void
	 */
	function init() {
		$default_options = (object) array('enabled'=>'no', 'title'=>'', 'api_user'=>'', 'api_key'=>'','account'=>'','contractid'=>'','source_postalcode'=>'','mode'=>'live', 'delivery'=>'', 'margin'=>'', 'packageweight'=>floatval('0.02'), 'boxes_enable'=> false, 'lettermail_enable'=> false, 'shipping_tracking'=> true, 'email_tracking'=> true, 'log_enable'=>false,'lettermail_limits'=>false,'lettermail_maxlength'=>'','lettermail_maxwidth'=>'','lettermail_maxheight'=>'', 
		    'tracking_icons'=> true, 'api_dev_user'=>'', 'api_dev_key'=>'', 'tracking_template'=>'', 'tracking_colcss'=>'width:21%;float:left;padding:4px;', 'tracking_msgcss'=>'width:50%;float:left;padding:4px;text-align:center;', 'tracking_action_email'=>'', 'tracking_action_customer'=>'','legacytracking'=>true, 'tracking_dateformat' => '', 'tracking_heading'=>'');
		$this->options		= get_option('woocommerce_cpwebservice', $default_options);
		$this->options		= (object) array_merge((array) $default_options, (array) $this->options); // ensure all keys exist, as defined in default_options.
		$this->enabled		= $this->options->shipping_tracking && ( !empty($this->options->api_user) && !empty($this->options->api_key) );
		$this->log 	        = (object) array('params'=>array(),'request'=>array('http'=>''), 'apierror'=> '');
		$this->pins			= array();
        $this->cached 		= 0;
        $this->options->legacytracking = apply_filters('cpwebservice_legacy_tracking', $this->options->legacytracking);

		if ($this->enabled) {
			// Actions
			add_action( 'add_meta_boxes', array(&$this, 'add_tracking_details_box') );
			add_action('wp_ajax_cpwebservice_update_order_tracking', array(&$this, 'update_order_tracking'));
            add_action('cpwebservice_tracking_lookup', array(&$this, 'lookup_tracking'), 10, 3 );
            add_action('wp_ajax_cpwebservice_migrate_tracking_legacy_data', array(&$this, 'migrate_tracking_legacy_data'));
            // Cron update action
            add_action('cpwebservice_scheduled_tracking', array(&$this, 'scheduled_update_tracked_orders'));
			// Tracking Display actions
			add_action($this->valid_display_action($this->options->tracking_action_customer, 'woocommerce_order_details_after_order_table'),  array(&$this, 'add_tracking_details_customer'), 1 );
			add_action($this->valid_display_action($this->options->tracking_action_email, 'woocommerce_email_after_order_table'),  array(&$this, 'add_tracking_details_customer'), 1 );
            
            add_filter( 'woocommerce_email_classes', array(&$this, 'tracking_email'));
            add_action( 'woocommerce_cpwebservice_tracking_notification', array(&$this, 'tracking_email_trigger'));
            // Order actions box on order edit page
            add_filter( 'woocommerce_order_actions', array(&$this, 'add_order_actions'), 15, 2 );
            add_action( 'woocommerce_order_action_cpwebservice_tracking_email', array(&$this, 'process_order_actions' ) );
		}

	}
	
	/*
	 * Return resources
	 */
	abstract function get_resource($id);
    
    // Cached Lookup
	public function get_tracking($order_id){
		if (!empty($order_id) && $this->cached != $order_id){
			$db = new woocommerce_cpwebservice_db();
			// Lookup Tracking data
			$this->pins = $db->tracking_get($order_id);
			$this->cached = $order_id;
		}
		return !empty($order_id) ? $this->pins : array();
	}

    // Cached Lookup
	public function get_tracking_pin($order_id, $pin){
		$pins = $this->get_tracking($order_id);
		if (!empty($pins)){
			foreach($pins as $index => $p){
				if ($p->pin == $pin){
					return $p;
				}
			}
		}
		return array();
	}

	public function save_tracking($order_id, $pin, $trackingData, $trackingEvents, $dateshipped, $datedelivered) {
		$db = new woocommerce_cpwebservice_db();
		$meta = !empty($trackingData) ? json_encode($trackingData) : '';
		$events = !empty($trackingEvents) ? json_encode($trackingEvents) : '';
		$db->tracking_save($order_id, $pin, $meta, $events, $dateshipped, $datedelivered);
		// Clear cache
		$this->cached = 0;
	}
	
	// Customer My Order page displays tracking information.
	public function add_tracking_details_customer($order) {
	    $order_id = is_int($order) ? $order : (method_exists($order, 'get_id') ? $order->get_id() : $order->id);
		// Lookup Tracking data
		$results = $this->get_tracking($order_id);
        $tracking = array();
        if (empty($results) && $this->options->legacytracking){
            $results = $this->get_tracking_legacy($order_id);
        }
		
		if (!empty($results) && is_array($results)){
			foreach($results as $pin){
				// Does cached lookup
				$tracking[] = $this->lookup_tracking($order_id, $pin->pin);
			}
            if (!empty($this->options->tracking_heading)){
                echo '<header><h2>'.esc_html( $this->options->tracking_heading ).'</h2></header>';
            } else {
			    echo '<header><h2>'.esc_html__( 'Order Shipping Tracking', 'woocommerce-canadapost-webservice' ).'</h2></header>';
            }
			echo $this->display_tracking($tracking, $order_id, false, false, true); // does not display admin btns./activates inline styles.
		}
	}

	/* Adds a box to the main column on the Post and Page edit screens */
	public function add_tracking_details_box() {
		add_meta_box( 'cpwebservice_tracking', __( 'Order Shipping Tracking', 'woocommerce-canadapost-webservice' ),  array(&$this,'display_tracking_view'), 'shop_order', 'normal', 'default' );
	}

	public function display_tracking_view($order){
		$order_id = is_int($order) ? $order : (method_exists($order, 'get_id') ? $order->get_id() : $order->ID);
		// Lookup Tracking data
		$results = $this->get_tracking($order_id);
        $tracking = array();
        if (empty($results) && $this->options->legacytracking){
            $results = $this->get_tracking_legacy($order_id);
        }
		?>
		<div id="cpwebservice_tracking_result">
		<?php 		
		if (!empty($results) && is_array($results)){
			foreach($results as $index => $pin){
				// Does cached lookup 
				$tracking[] = $this->lookup_tracking($order_id, $pin->pin);
			}
	
			echo $this->display_tracking($tracking, $order_id, false, true);
		}
		?>
		</div>
		<ul> 
		<li><img src="<?php echo plugins_url( $this->get_resource('method_logo_url') , dirname(__FILE__) ); ?>" style="vertical-align:middle" /> <input type="text" class="input-text" size="22" name="cpwebservice_trackingid" id="cpwebservice_trackingid" placeholder="" value="" /> 
		<a href="<?php echo wp_nonce_url( admin_url( 'admin-ajax.php?action=cpwebservice_update_order_tracking&order_id=' . $order_id ), 'cpwebservice_update_order_tracking' ); ?>&trackingno=" class="button tips canadapost-tracking" target="_blank" title="<?php esc_attr_e( 'Add Tracking Pin', 'woocommerce-canadapost-webservice' ); ?>" data-tip="<?php esc_attr_e( 'Add Tracking Pin', 'woocommerce-canadapost-webservice' ); ?>">
		<?php esc_html_e( 'Add Tracking Pin', 'woocommerce-canadapost-webservice' ); ?> 
		</a> <div class="cpwebservice_ajaxsave canadapost-spinner" style="display: none;"><div class="canadapost-spinner-b1"></div><div class="canadapost-spinner-b2"></div><div class="canadapost-spinner-b3"></div></div> </li>
		</ul>
		<?php wp_nonce_field( plugin_basename( __FILE__ ), 'cpwebservice_tracking_noncename' ); ?>
		<?php 
	}
	
	/*
	 * Tracking url from Api
	 */
	abstract public function tracking_url($pin, $locale);
	
	
	
	/* Does Lookup & Displays Tracking information */
	public function display_tracking($tracking, $order_id, $only_rows=false, $display_admin=false, $inline_styles=false){
		// Locale for Link to Service.
		$locale = $this->get_locale();

		// Inline styles (for email)
		// row
		$inline_row = $inline_styles ? 'clear:both;margin-bottom:4px;' : '';
		// Columns
		$inline = $inline_styles ? $this->options->tracking_colcss : '';
		$inline_pin = $inline_styles ? 'font-size:110%;white-space:nowrap;min-width:154px;'.$inline : '';
		$inline_message = $inline_styles ? $this->options->tracking_msgcss : '';
		
        if (!$display_admin && !empty($this->options->tracking_template)){
            if ($this->options->tracking_template == 'minimal'){
                return $this->display_tracking_layout_minimal($tracking, $order_id, $locale, $inline_row, $inline, $inline_pin, $inline_message);
            }
            else if ($this->options->tracking_template == 'responsive'){
                return $this->display_tracking_layout_responsive($tracking, $order_id, $locale,$inline_row, $inline, $inline_pin, $inline_message);
            }
            else if ($this->options->tracking_template == 'progress'){
                return $this->display_tracking_layout_progress($tracking, $order_id, $locale,$inline_row, $inline, $inline_pin, $inline_message);
            }
        } // else Main Tracking details template.


		// Display Tracking info:
        $html = '';
        // Get Output, return as string.
	    ob_start();
		if (count($tracking) > 0){		    
		    ?><div class="widefat canadapost-tracking-display">
		    
			<?php if (!$only_rows): ?>
			<?php if (!(!$display_admin && !empty($this->options->tracking_template) && $this->options->tracking_template == 'minimal')): ?>
			<div class="canadapost-tracking-header" style="<?php echo esc_attr($inline_row)?>"><?php if ($display_admin): ?><div class="canadapost-tracking-col canadapost-tracking-col-sm" style="<?php echo esc_attr($inline)?>"></div><?php endif; ?><div class="canadapost-tracking-col" style="<?php echo esc_attr($inline_pin)?>"><?php esc_html_e( 'Tracking Number', 'woocommerce-canadapost-webservice' )?></div><div class="canadapost-tracking-col" style="<?php echo esc_attr($inline)?>"><?php esc_html_e( 'Event', 'woocommerce-canadapost-webservice' )?></div><div class="canadapost-tracking-col" style="<?php echo esc_attr($inline)?>"><?php esc_html_e( 'Shipping Service', 'woocommerce-canadapost-webservice' )?></div><div class="canadapost-tracking-col" <?php echo esc_attr($inline) ?>><?php esc_html_e( 'Shipment', 'woocommerce-canadapost-webservice' ) ?> / <?php _e( 'Delivery', 'woocommerce-canadapost-webservice' ) ?></div>
			     <br style="clear:both" />
			</div>
			<?php endif; ?>
			<?php endif; ?>
			<?php foreach ($tracking as $trackingRow) {
				if (count($trackingRow) > 0){
					foreach($trackingRow as $track){ ?>
						<div class="canadapost-tracking-row cpwebservice_track_<?php echo esc_attr($track['pin']) ?>" style="<?php echo esc_attr($inline_row)?>">
						<?php if ($display_admin): ?>
							<div class="canadapost-tracking-col canadapost-tracking-col-sm" style="<?php echo esc_attr($inline)?>">
							<a href="<?php echo wp_nonce_url( admin_url( 'admin-ajax.php?action=cpwebservice_update_order_tracking&refresh_row=1&order_id=' . $order_id.'&trackingno='.esc_attr($track['pin']) ), 'cpwebservice_update_order_tracking' ) ?>" class="button canadapost-btn-icon cpwebservice_refresh" data-pin="<?php echo esc_attr($track['pin']) ?>" title="<?php esc_html_e('Update','woocommerce-canadapost-webservice')?>"><span class="dashicons dashicons-update"></span></a> 
							<a href="<?php echo wp_nonce_url( admin_url( 'admin-ajax.php?action=cpwebservice_update_order_tracking&remove_tracking=1&order_id=' . $order_id.'&trackingno='.esc_attr($track['pin']) ), 'cpwebservice_update_order_tracking' )?>" class="button canadapost-btn-icon cpwebservice_remove" data-pin="<?php echo esc_attr($track['pin'])?>" title="<?php esc_html_e('Remove','woocommerce-canadapost-webservice')?>"><span class="dashicons dashicons-no"></span></a></div>
						<?php endif; ?>
						<?php if (!$display_admin && !empty($this->options->tracking_template) && $this->options->tracking_template == 'minimal'): ?>
							<div class="canadapost-tracking-col shipping-trackingno">
    						    <a href="<?php echo esc_attr($this->tracking_url($track['pin'], $locale)) ?>" target="_blank" class="canadapost-tracking-link">
    						      <?php if ($this->options->tracking_icons) : ?><img src="<?php echo plugins_url( 'img/shipped.png' , dirname(__FILE__) )?>" width="16" height="16" border="0" style="vertical-align:middle" alt="Tracking" /><?php endif; ?>
    						    <?php echo esc_html($track['pin']) ?></a>
    						</div>
            		    <?php else: ?>
    						<div class="canadapost-tracking-col shipping-trackingno" style="<?php echo esc_attr($inline_pin)?>">
    						    <a href="<?php echo esc_attr($this->tracking_url($track['pin'], $locale)) ?>" target="_blank" class="canadapost-tracking-link">
    						      <?php if ($this->options->tracking_icons) : ?><img src="<?php echo esc_attr(plugins_url( 'img/shipped.png' , dirname(__FILE__) ))?>" width="16" height="16" border="0" style="vertical-align:middle" alt="Tracking" /><?php endif; ?>
    						    <?php echo esc_html($track['pin']) ?></a>
    						</div>
    						<?php if (!empty($track['event-description']) && !empty($track['event-date-time'])): ?>
    							<div class="canadapost-tracking-col shipping-eventinfo" style="<?php echo esc_attr($inline)?>">
    							<?php echo esc_html($track['event-description']) ?>
    							  <br /><?php echo esc_html($this->format_tracking_date($track['event-date-time'])) . ' ' . esc_html($track['event-location']) ?></div>
    							<div class="canadapost-tracking-col shipping-servicename" style="<?php echo esc_attr($inline)?>">
    							<?php if ($this->options->tracking_icons) { ?><img src="<?php echo esc_attr(plugins_url( $this->get_resource('shipment_icon') , dirname(__FILE__) )) ?>"  style="vertical-align:middle" /><br /><?php } else { echo esc_html($this->get_resource('method_title')); } ?>
    							<?php echo esc_html($track['service-name']) ?></div>
    							<div class="canadapost-tracking-col shipping-delivered" style="<?php echo esc_attr($inline)?>">
    							 <?php esc_html_e('Shipped', 'woocommerce-canadapost-webservice') ?>: <strong><?php echo esc_html($this->format_tracking_date($track['mailed-on-date']))?></strong>
    							    <?php echo esc_html($track['origin-postal-id'])?><?php if (!empty($track['destination-postal-id'])) : ?> <?php esc_html_e('to', 'woocommerce-canadapost-webservice')?> <?php endif; ?><?php echo esc_html($track['destination-postal-id']) ?>
    							     <?php if ($track['actual-delivery-date']) { ?> 
    								  <br /><?php esc_html_e('Delivered','woocommerce-canadapost-webservice')?>: <strong><?php echo esc_html($this->format_tracking_date($track['actual-delivery-date']))?></strong>
    							     <?php } else if ($track['expected-delivery-date']) { ?>
    								 <br /><?php esc_html_e('Expected Delivery','woocommerce-canadapost-webservice')?>: <strong><?php echo esc_html($this->format_tracking_date($track['expected-delivery-date'])) ?></strong>
    								 <?php } // endif?>
    								 <?php if (!empty($track['customer-ref-1'])): ?>
    								 <br /><?php esc_html_e( 'Reference', 'woocommerce-canadapost-webservice' )?>: <strong><?php echo esc_html($track['customer-ref-1']) ?></strong>
    								 <?php endif; ?>
    							</div>
    						<?php else: ?>
    							<div class="canadapost-tracking-col-message" style="<?php echo esc_attr($inline_message) ?>"><p class="description"><?php esc_html_e( 'No Tracking Data Found', 'woocommerce-canadapost-webservice' )?></p></div>
    						<?php endif; ?>
						
            		    <?php endif; ?>
						<br style="clear:both" />
						</div>
						<?php 
					} // end foreach
				} // endif
			} // end foreach ?>
			</div>
		<?php 
		} // endif
		// Return display html
		$html = ob_get_contents();
		ob_end_clean();
		
		return $html;
	}

    public function display_tracking_layout_minimal($tracking, $order_id, $locale, $inline_row, $inline, $inline_pin, $inline_message){
        // Display Tracking info:
        $html = '';
        // Get Output, return as string.
        ob_start();
        if (count($tracking) > 0){
        ?>
        <div class="widefat canadapost-tracking-display">
        <?php foreach ($tracking as $trackingRow) {
				if (count($trackingRow) > 0){
					foreach($trackingRow as $track){ ?>
                    <div class="canadapost-tracking-row cpwebservice_track_<?php echo esc_attr($track['pin']) ?>" style="<?php echo esc_attr($inline_row)?>">
                    <div class="canadapost-tracking-col shipping-trackingno">
                        <?php if($this->options->tracking_icons): ?>🚚<?php endif; ?>
                        <a href="<?php echo esc_attr($this->tracking_url($track['pin'], $locale)) ?>" target="_blank" class="canadapost-tracking-link"><?php echo esc_html($track['pin']) ?></a>
                    </div>
                    <?php
                    } // end foreach
				} // endif
			} // end foreach
            ?>
        </div>
        <?php
        } // endif
        // Return display html
		$html = ob_get_contents();
		ob_end_clean();
		
		return $html;
    }

    public function display_tracking_layout_responsive($tracking, $order_id, $locale, $inline_row, $inline, $inline_pin, $inline_message) {
        // Display Tracking info:
        if ($inline){
            $inline = 'width:32%;float:left;padding:4px;';
        }
        $html = '';
        // Get Output, return as string.
        ob_start();
        if (count($tracking) > 0){
        ?>
        <div class="widefat canadapost-tracking-display">
        <style type="text/css">
        @media screen and (max-width:800px) {
            .canadapost-tracking-col {
                width:100% !important;
                float: none;
            }
        }    
        </style>
        <?php foreach ($tracking as $trackingRow) {
                if (count($trackingRow) > 0){
                    foreach($trackingRow as $track){ ?>
                          <div class="canadapost-tracking-row cpwebservice_track_<?php echo esc_attr($track['pin']) ?>" style="<?php echo esc_attr($inline_row)?>">
    						<div class="canadapost-tracking-col shipping-trackingno" style="<?php echo esc_attr($inline_pin)?>">
    						    <a href="<?php echo esc_attr($this->tracking_url($track['pin'], $locale)) ?>" target="_blank" class="canadapost-tracking-link">
    						      <?php if ($this->options->tracking_icons) : ?>🚚<?php endif; ?>
    						    <?php echo esc_html($track['pin']) ?></a>
    						</div>
                         </div>
                         <div class="canadapost-tracking-row cpwebservice_track_<?php echo esc_attr($track['pin']) ?>" style="<?php echo esc_attr($inline_row)?>">
    						<?php if (!empty($track['event-description']) && !empty($track['event-date-time'])): ?>
    							<div class="canadapost-tracking-col shipping-eventinfo" style="<?php echo esc_attr($inline)?>">
    							<?php echo esc_html($track['event-description']) ?>
    							  <br /><?php echo esc_html($this->format_tracking_date($track['event-date-time'])) . ' ' . esc_html($track['event-location']) ?></div>
    							<div class="canadapost-tracking-col shipping-servicename" style="<?php echo esc_attr($inline)?>">
    							<?php if ($this->options->tracking_icons) { ?><img src="<?php echo esc_attr(plugins_url( $this->get_resource('shipment_icon') , dirname(__FILE__) )) ?>"  style="vertical-align:middle" /><br /><?php } else { echo esc_html($this->get_resource('method_title')); } ?>
    							<?php echo esc_html($track['service-name']) ?></div>
    							<div class="canadapost-tracking-col shipping-delivered" style="<?php echo esc_attr($inline)?>">
    							 <?php esc_html_e('Shipped', 'woocommerce-canadapost-webservice') ?>: <strong><?php echo esc_html($this->format_tracking_date($track['mailed-on-date']))?></strong>
    							    <?php echo esc_html($track['origin-postal-id'])?><?php if (!empty($track['destination-postal-id'])) : ?> <?php esc_html_e('to', 'woocommerce-canadapost-webservice')?> <?php endif; ?><?php echo esc_html($track['destination-postal-id']) ?>
    							     <?php if ($track['actual-delivery-date']) { ?> 
    								  <br /><?php esc_html_e('Delivered','woocommerce-canadapost-webservice')?>: <strong><?php echo esc_html($this->format_tracking_date($track['actual-delivery-date']))?></strong>
    							     <?php } else if ($track['expected-delivery-date']) { ?>
    								 <br /><?php esc_html_e('Expected Delivery','woocommerce-canadapost-webservice')?>: <strong><?php echo esc_html($this->format_tracking_date($track['expected-delivery-date'])) ?></strong>
    								 <?php } // endif?>
    								 <?php if (!empty($track['customer-ref-1'])): ?>
    								 <br /><?php esc_html_e( 'Reference', 'woocommerce-canadapost-webservice' )?>: <strong><?php echo esc_html($track['customer-ref-1']) ?></strong>
    								 <?php endif; ?>
                                </div>
    						<?php endif; ?>
						<br style="clear:both" />
						</div>
                    <?php
                    } // end foreach
                } // endif
            } // end foreach
            ?>
        </div>
        <?php
        } // endif
        // Return display html
        $html = ob_get_contents();
        ob_end_clean();

        return $html;
    }
	
    public function display_tracking_layout_progress($tracking, $order_id, $locale, $inline_row, $inline, $inline_pin, $inline_message) {
        // Display Tracking info:
        if ($inline){
            $inline = 'width:48%;float:left;padding:4px;';
        }
        if ($inline_pin){
            $inline_pin = 'font-size:110%;white-space:nowrap;min-width:154px;';
        }
        $inline_progress = 'margin-top:12px; margin-bottom:6px;';
        $inline_progress_img = 'vertical-align:middle; margin:auto; width: 90%;';
        $html = '';
        // Get Output, return as string.
        ob_start();
        if (count($tracking) > 0){
        ?>
        <div class="widefat canadapost-tracking-display">
        <?php foreach ($tracking as $trackingRow) {
				if (count($trackingRow) > 0){
					foreach($trackingRow as $track){ ?>
                        <div class="canadapost-tracking-row cpwebservice_track_<?php echo esc_attr($track['pin']) ?>" style="<?php echo esc_attr($inline_row)?>">
                            <div class="canadapost-tracking-progress" style="text-align:center;<?php echo esc_attr($inline_progress) ?>">
                                <?php if(!empty($track['actual-delivery-date'])): ?>
                                <img src="<?php echo plugins_url( 'img/tracking-progress-3.png' , dirname(__FILE__) )?>" width="460" height="33" style="<?php echo esc_attr($inline_progress_img) ?>" alt="<?php esc_html_e('Delivered', 'woocommerce-canadapost-webservice') ?>" />
                                <?php elseif(!empty($track['mailed-on-date'])): ?>
                                <img src="<?php echo plugins_url( 'img/tracking-progress-2.png' , dirname(__FILE__) )?>" width="460" height="33" style="<?php echo esc_attr($inline_progress_img) ?>" alt="<?php esc_html_e('Shipped', 'woocommerce-canadapost-webservice') ?>" />
                                <?php else: ?>
                                <img src="<?php echo plugins_url( 'img/tracking-progress-1.png' , dirname(__FILE__) )?>" width="460" height="33" style="<?php echo esc_attr($inline_progress_img) ?>" alt="<?php esc_html_e('Tracking', 'woocommerce-canadapost-webservice') ?>" />
                                <?php endif; ?>
                            </div>
                            <div style="width:31%;float:left;margin-top:4px;text-align:left;"><?php esc_html_e('Ordered', 'woocommerce-canadapost-webservice') ?></div>
                            <div style="width:31%;float:left;margin-top:4px;text-align:center;"><?php if(!empty($track['mailed-on-date'])): ?><?php esc_html_e('Shipped', 'woocommerce-canadapost-webservice') ?><br /><?php echo esc_html($this->format_tracking_date($track['mailed-on-date'])) ?><?php else: ?><?php esc_html_e('Shipping', 'woocommerce-canadapost-webservice') ?><?php endif; ?></div>
                            <div style="width:31%;float:left;margin-top:4px;text-align:right;"><?php if(!empty($track['actual-delivery-date'])): ?><?php esc_html_e('Delivered', 'woocommerce-canadapost-webservice') ?><br /><?php echo esc_html($this->format_tracking_date($track['actual-delivery-date'])) ?><?php elseif(!empty($track['expected-delivery-date'])): ?><?php esc_html_e('Estimated Delivery', 'woocommerce-canadapost-webservice') ?><br /><?php echo esc_html($this->format_tracking_date($track['expected-delivery-date'])) ?><?php endif; ?></div>
                            <br style="clear:both" />
                        </div>
                        <div class="canadapost-tracking-row cpwebservice_track_<?php echo esc_attr($track['pin']) ?>" style="<?php echo esc_attr($inline_row)?>">
                            <div class="canadapost-tracking-row shipping-trackingno" style="<?php echo esc_attr($inline_pin)?>">
                                <a href="<?php echo esc_attr($this->tracking_url($track['pin'], $locale)) ?>" target="_blank" class="canadapost-tracking-link">
                                    <?php if ($this->options->tracking_icons) : ?>🚚<?php endif; ?>
                                <?php echo esc_html($track['pin']) ?></a>
                            </div>
                        </div>
                        <div class="canadapost-tracking-row cpwebservice_track_<?php echo esc_attr($track['pin']) ?>" style="<?php echo esc_attr($inline_row)?>">
    						<?php if (!empty($track['event-description']) && !empty($track['event-date-time'])): ?>
    							<div class="canadapost-tracking-col shipping-eventinfo" style="<?php echo esc_attr($inline)?>">
                                <?php if ($this->options->tracking_icons) { ?><img src="<?php echo esc_attr(plugins_url( $this->get_resource('shipment_icon') , dirname(__FILE__) )) ?>"  style="vertical-align:middle" /><br /><?php } else { echo esc_html($this->get_resource('method_title')); } ?>
    							<?php echo esc_html($track['service-name']) ?>
    							<?php echo esc_html($track['event-description']) ?>
    							  <br /><?php echo esc_html($this->format_tracking_date($track['event-date-time'])) . ' ' . esc_html($track['event-location']) ?></div>
    							<div class="canadapost-tracking-col shipping-delivered" style="<?php echo esc_attr($inline)?>">
    							<br /><?php esc_html_e('Shipped', 'woocommerce-canadapost-webservice') ?>: <strong><?php echo esc_html($this->format_tracking_date($track['mailed-on-date']))?></strong>
    							    <br /><?php echo esc_html($track['origin-postal-id'])?><?php if (!empty($track['destination-postal-id'])) : ?> <?php esc_html_e('to', 'woocommerce-canadapost-webservice')?> <?php endif; ?><?php echo esc_html($track['destination-postal-id']) ?>
    							     <?php if ($track['actual-delivery-date']) { ?> 
    								  <br /><?php esc_html_e('Delivered','woocommerce-canadapost-webservice')?>: <strong><?php echo esc_html($this->format_tracking_date($track['actual-delivery-date']))?></strong>
    							     <?php } else if ($track['expected-delivery-date']) { ?>
    								 <br /><?php esc_html_e('Expected Delivery','woocommerce-canadapost-webservice')?>: <strong><?php echo esc_html($this->format_tracking_date($track['expected-delivery-date'])) ?></strong>
    								 <?php } // endif?>
    								 <?php if (!empty($track['customer-ref-1'])): ?>
    								 <br /><?php esc_html_e( 'Reference', 'woocommerce-canadapost-webservice' )?>: <strong><?php echo esc_html($track['customer-ref-1']) ?></strong>
    								 <?php endif; ?>
    							</div>
    						<?php else: ?>
    							<div class="canadapost-tracking-col-message" style="<?php echo esc_attr($inline_message) ?>"><p class="description"><?php esc_html_e( 'No Tracking Data Found', 'woocommerce-canadapost-webservice' )?></p></div>
    						<?php endif; ?>
						    <br style="clear:both" />
						</div> 
                    <?php
                    } // end foreach
				} // endif
			} // end foreach
            ?>
        </div>
        <?php
        } // endif
        // Return display html
		$html = ob_get_contents();
		ob_end_clean();
		
		return $html;
    }

    public function get_locale(){
        // Locale for Link to Service.
		$locale = 'en'; // 'en' : 'fr';
		if (defined('ICL_LANGUAGE_CODE')){
			$locale = (ICL_LANGUAGE_CODE=='fr') ? 'fr':'en'; // 'en' is default
		} else if (get_locale() == 'fr_FR' || get_locale() == 'fr_CA'){
			$locale = 'fr';
		} else {
			$locale = 'en';
		}
        return $locale;
    }
	
	
	/**
	 * Load and generate the template output with ajax
	 */
	public function update_order_tracking() {
		// Permissions:  Admin area only access the page
		if( !is_admin() &&
			// Check the user privileges
			!current_user_can( 'manage_woocommerce_orders' ) && !current_user_can( 'edit_shop_orders' ) &&
			// Check the action
			(empty( $_GET['action'] ) || !check_admin_referer( $_GET['action'] ) ) &&
			// Check if all parameters are set
			(empty($_GET['trackingno'] ) || empty($_GET['order_id']))
		) {
			wp_die( esc_attr__( 'You do not have sufficient permissions to access this page.' ) );
		}
		
		// Nonce.
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'cpwebservice_update_order_tracking' ) )
			return;
			
		// Get tracking no, order_id
		$trackingnumber = sanitize_text_field( $_GET['trackingno'] );
		$order_id = intval($_GET['order_id']);
		
		// Remove spaces and dashes from tracking number
		$trackingnumber = preg_replace('/[\r\n\t \-]+/', '', $trackingnumber);
		
		// Current tracking pins:
		$db = new woocommerce_cpwebservice_db();
		$trackingPins = $db->tracking_get_pins($order_id);
		
		// Do action: Refresh
		if( !empty( $_GET['refresh_row'] ) && !empty($trackingPins) ) {
			$t = $this->lookup_tracking($order_id, $trackingnumber, true); // force refresh.
			echo $this->display_tracking(array($t),$order_id, true, true);
			exit;
		}
		
		// Do action: Remove
		if( !empty( $_GET['remove_tracking'] ) && !empty($trackingPins) ) {
			$exists = in_array($trackingnumber, $trackingPins);			

			// Remove tracking data
			$db->tracking_remove($order_id, $trackingnumber);
			
			esc_html_e('Removed.', 'woocommerce-canadapost-webservice');
			exit;
		}
		
		// Do action: Add
		if (empty($trackingPins) || !in_array($trackingnumber, $trackingPins)){ // ensures pin isn't added twice.
			
			// Lookup, cache & display tracking
			$t = $this->lookup_tracking($order_id, $trackingnumber);
			echo $this->display_tracking(array($t),$order_id, (count($trackingPins)!=1), true);
			
			exit;
		}
		
		esc_html_e('Duplicate Pin.', 'woocommerce-canadapost-webservice');
		
		exit;
	}
	
	/*
	 * Lookup Tracking from Api
	 * Saves Data
	 * Returns Tracking Meta
	 */
	abstract public function lookup_tracking($order_id, $trackingPin, $refresh=false);
	
	
	/*
	 * Gets Tracking Meta decoded
	 */
	public function get_tracking_meta($pin){
	    // Decode metadata
       return (!empty($pin) && isset($pin->meta) && !empty($pin->meta)) ? json_decode($pin->meta, true) : array();
	}
    
    // Legacy Package Shipment Tracking data 
    public function get_tracking_legacy($order_id){
        $tracking = array();
        $db = new woocommerce_cpwebservice_db();
        $pins = $db->tracking_get_legacy($order_id);
        if (!empty($pins) && is_array($pins)){
            $meta = $db->tracking_get_legacy_meta($order_id);

            // Add any pins that only have meta-data
            if (!empty($meta) && is_array($meta)){
                foreach ($meta as $pin=>$m) {
                    if (!in_array($pin, $pins)){
                        $pins[] = $pin;
                    }
                }
            }
            // Process Pins
            foreach($pins as $pin) {
                $tracking[$pin] = (object) array('pin'=> $pin, 'meta' => array(), 'dateupdated'=> date('Y-m-d H:i:s'), 'dateshipped'=>'','datedelivered'=>'');
                if (!empty($meta) && is_array($meta) && isset($meta[$pin]) && is_array($meta[$pin])){
                    $tracking[$pin]->meta = $meta[$pin];
                    // Use first array item
                    $latest_meta = $meta[$pin][0];
                    if (!empty($latest_meta)){
                        $tracking[$pin]->dateshipped = !empty($latest_meta['mailed-on-date']) ? $latest_meta['mailed-on-date'] : '';
                        $tracking[$pin]->datedelivered = !empty($latest_meta['actual-delivery-date']) ? $latest_meta['actual-delivery-date'] : '';
                    }
                }
            }
            
            // Move values
            foreach ($tracking as $pin=>$data) {
                $this->save_tracking($order_id, $pin, $data->meta, null, $data->dateshipped, $data->datedelivered);
            }
            if ($db->no_errors()){
                $db->tracking_remove_legacy($order_id);
            }
        }

        return $tracking;
    }

    public function migrate_tracking_legacy_data(){
        if( !is_admin() &&
			// Check the user privileges
			!current_user_can( 'manage_woocommerce_orders' ) && !current_user_can( 'edit_shop_orders' ) &&
			// Check the action
			(empty( $_GET['action'] ) || !check_admin_referer( $_GET['action'] ) )
		) {
			wp_die( esc_attr__( 'You do not have sufficient permissions to access this page.' ) );
		}
		
		// Nonce.
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'cpwebservice_migrate_tracking_legacy_data' ) )
            return;
            
        // Moves any orders with legacy data.
        $db = new woocommerce_cpwebservice_db();
        $order_ids = $db->tracking_get_all_legacy_order_ids();
        if (!empty($order_ids) && is_array($order_ids)){
            foreach ($order_ids as $id) {
                $_ = $this->get_tracking_legacy($id);
            }
        } 
        
        // Load up woocommerce shipping stack.
	    do_action('woocommerce_shipping_init');
	    // Shipping method class
        $shippingmethod = new woocommerce_cpwebservice();
        // Turn off Legacy tracking
        $shippingmethod->options->legacytracking = false;
        update_option('woocommerce_cpwebservice', $shippingmethod->options);

        esc_html_e('Complete.', 'woocommerce-canadapost-webservice');
        
		exit;
    }

    // Order Actions (Add email notification)
    public function add_order_actions($actions, $order = null) {
        // Get Order ID (compatibility all WC versions)
        $order_id = 0;
        if ($order != null){
            $order_id = method_exists( $order, 'get_id' ) ? $order->get_id() : $order->id;
        } else {
            global $theorder;
            if ($theorder != null){
            $order_id = method_exists( $theorder, 'get_id' ) ? $theorder->get_id() : $theorder->id;
            }
        }
        $db = new woocommerce_cpwebservice_db();
        if (!empty($db->tracking_get_pins($order_id))){
            $actions['cpwebservice_tracking_email'] = __( 'Send tracking email for ', 'woocommerce-canadapost-webservice' ) . $this->get_resource('method_title');
        }
        return $actions;
    }

    // Run Order Action (Email notification)
    public function process_order_actions($order){
        // Trigger email notification
        $order_id = method_exists( $order, 'get_id' ) ? $order->get_id() : $order->id;
        do_action('woocommerce_cpwebservice_tracking_notification', $order_id);
    }

    public function tracking_email($email_classes) {
        if (!class_exists('woocommerce_cpwebservice_trackingemail')) {
            // Load Class
            require_once(CPWEBSERVICE_PLUGIN_PATH . '/framework/trackingemail.php');
            require_once(CPWEBSERVICE_PLUGIN_PATH . '/models/woocommerce_cpwebservice_trackingemail.php');
        }
        $this->tracking_email = new woocommerce_cpwebservice_trackingemail();

        $email_classes['woocommerce_cpwebservice_trackingemail'] = $this->tracking_email;

	    return $email_classes;
    }
    public function tracking_email_trigger($order_id)
    {
        if ($this->tracking_email == null){
            $wc_emails = WC_Emails::instance();
        }

        $this->tracking_email->trigger($order_id);
    }

	// This function runs on a regular basis to update recent orders that have tracking attached.
	// It will send an email if configured.
	public function scheduled_update_tracked_orders() {

        // If Tracking not enabled
        if (!$this->options->shipping_tracking){
            return;
        }

        // List of Order Ids
		$orders = array();
        $do_email = false;
        $db = new woocommerce_cpwebservice_db();

        if (function_exists('wc_get_orders')){ // WC 3.x

            $orders = wc_get_orders( array(
                //Tracking on orders updated in the last 30 days.
                'date_created' => '>' .date("Y-m-d",time() - 30 * DAY_IN_SECONDS),
                'return' => 'ids', // return order ids instead of 'objects'
                'limit' => 250,
                'orderby' => 'date_created',
                'order' => 'DESC',
                'status' => array('wc-processing', 'wc-on-hold','wc-completed')));

        } else { 
            // Legacy (WC 2.x) Using get_posts
            $orders = get_posts( array(
                    'fields' => 'ids', // Only get post IDs
                    'numberposts' => 50,
                    'offset' => 0,
                    'orderby' => 'post_date',
                    'order' => 'DESC',
                    'post_type' => 'shop_order',
                    'date_query' => array(
                        'after' => date('Y-m-d', date("Y-m-d",time() - 30 * DAY_IN_SECONDS)) 
                    ),
                    'tax_query' => array(
                        array(
                            'taxonomy' => 'shop_order_status',
                            'field' => 'slug',
                            'terms' => array('pending','processing','completed')
                        )
                    )
            ) );
        }
		if (!empty($orders)) {            
            // Find orders with Package Tracking

            $batch = array_chunk($orders, 30); // Batch 30 at a time.
            foreach($batch as $orderbatch){

                // Tracking data
                $tracking_list = $db->tracking_get_multi($orderbatch);

                foreach( $orderbatch as $order_id ) { 
                    $do_email = false;
                    // Check for tracking numbers.
                    $tracking = $this->tracking_where_order_id($tracking_list, $order_id);
                    
                    if (!empty($tracking) && is_array($tracking)){
                        foreach($tracking as $pin){
                            // Update tracking that has not been delivered yet.
                            // If data is older than 1 day but less than 30 days, do update.
                            if (empty($pin->datedelivered) && !empty($pin->dateupdated) && !empty($pin->pin))
                            {
                                $diff = time() - strtotime($pin->dateupdated);
                                if ($diff > 0 && $diff > DAY_IN_SECONDS && $diff < DAY_IN_SECONDS * 30 )
                                {
                                    // Throttle 0.5s  (minimum 250ms)
                                    usleep(500000);

                                    // Update Tracking, Saves Api result.
                                    $t = $this->lookup_tracking($order_id, $pin->pin, true);
                                    $trackingUpdated = $db->tracking_get_details($order_id, $pin->pin);	

                                    // Compare to current data
                                    if (!empty($trackingUpdated) && !empty($trackingUpdated[0]) && is_object($trackingUpdated[0])){
                                              
                                        // Compare 'date shipped', if it is now a value, then an email notification should go out.
                                        // Only send notification if event has happened within 48 hrs.
                                        if (empty($pin->dateshipped) && !empty($trackingUpdated[0]->dateshipped) 
                                            && (time() - strtotime($trackingUpdated[0]->dateshipped) < DAY_IN_SECONDS * 2)) {
                                            // Send out email notification for this order.
                                            $do_email = true;
                                        }
                                        // Tracking data could have just been added, so if previous date updated is < 20 hrs (because this cron is daily)
                                        // And If shipped within 24hrs then send notification email because it is new.
                                        else if (
                                            !empty($trackingUpdated[0]->dateshipped)
                                            && (time() - strtotime($pin->dateupdated) < DAY_IN_SECONDS - 240 * 60) // within 20 hrs
                                            && (time() - strtotime($trackingUpdated[0]->dateshipped) < DAY_IN_SECONDS) // within 24 hrs
                                            ){
                                            // Send out email notification for this order.
                                            $do_email = true;
                                        }
                                        
                                        // Compare 'delivery date', if it is now a value (and was not before), then an email notification should go out.
                                        // Only send notification if event has happened within 48 hrs.
                                        elseif (empty($pin->datedelivered) && !empty($trackingUpdated[0]->datedelivered) 
                                            && (time() - strtotime($trackingUpdated[0]->datedelivered) < DAY_IN_SECONDS * 2)){
                                            // Send out email notification for this order.
                                            $do_email = true;
                                        }

                                    }

                                }// end if within specified update time.
                            }
                            
                        }
                        
                    }

                    // If email notification should be sent
                    if ($this->options->email_tracking && $do_email){
                        // Trigger email notification
                        $this->tracking_email_trigger($order_id); //can also do_action('woocommerce_cpwebservice_tracking_notification', $order_id);
                    }
                            
                } // endforeach
            }// endforeach
		}

	}

    private function tracking_where_order_id($tracking_list, $order_id){
        $tracking = array();
        foreach ($tracking_list as $t) {
            if ($t->order_id == $order_id){
                $tracking[] = $t;
            }
        }
        return $tracking;
    }
	
	public function valid_display_action( $action, $default ){
	    // Returns a valid display action
	    if (!empty($action)){
	        // Allowed actions:
	        if (  $action == 'woocommerce_order_details_after_order_table' // default
			   || $action == 'woocommerce_email_after_order_table'         // default
			   || $action == 'woocommerce_email_before_order_table' 
			   || $action == 'woocommerce_view_order'    ){
	            return $action;
	        }
	        
	    }
	    
	    return $default;
    }


    private $tracking_date_formats = array('Y-m-d', 'm/d/Y', 'm/d/Y g:i a', 'M j, Y', 'M j, Y g:i a', 'F j, Y', 'F j, Y, g:i a', 'l M j, Y g:i a');
    public function format_tracking_date($datestring){
        if (!empty($datestring) && !empty($this->options->tracking_dateformat) 
             && in_array($this->options->tracking_dateformat, $this->tracking_date_formats)){
            return (new DateTime($datestring))->format($this->options->tracking_dateformat);
        }
        return $datestring;
    }
	
}