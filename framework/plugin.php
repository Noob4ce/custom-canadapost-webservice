<?php
/*
 Plugin Startup class
 plugin.php
 
 Copyright (c) 2017-2022 Jamez Picard
 
 */
abstract class cpwebservice_plugin
{
    
    /**
     * __construct public function.
     *
     * @access public
     * @return cpwebservice_plugin
     */
    public function __construct() {
        $this->load();
    }
    
    /*
     * Return resources
     */
    abstract function get_resource($id);
    
    /**
     * init public function.
     *
     * @access public
     * @return void
     */
    public function load() {
       
        // Shipping Method Init Action
        add_action('woocommerce_shipping_init', array(&$this, 'shipping_init'), 0);

        // Shipping Calculator: Require postal code option
        add_action('woocommerce_before_shipping_calculator', array(&$this,'cpwebservice_init_require_postal_code'));
        
        // Hooks filter woocommerce_cart_shipping_method_full_label to allow for better formatting.
        add_filter('woocommerce_cart_shipping_method_full_label', array(&$this,'cpwebservice_shipping_method_label') );

        // Database integration
        add_action('init', array(&$this, 'cpwebservice_db'));

        // Wire up product shipping options
        // Admin: Product Management
        add_action( 'admin_init', array(&$this,'load_product_options')); 
        
        // Load Localisation
        add_action( 'plugins_loaded', array(&$this, 'load_localisation'));
        
        // Checkout order details
        add_action( 'woocommerce_shipping_init', 	array(&$this, 'orderdetails_init'));
        add_action( 'admin_init', 				    array(&$this, 'orderdetails_init'));
        
        // Order Shipments
        add_action( 'admin_init',          array(&$this,'shipments_init'));
        add_action( 'wp_scheduled_delete', array(&$this,'shipments_init' )); // for dbcache
        
        // Admin Ajax actions in Shipment Settings.
        if (is_admin()){
            // Ajax Validate Action
            add_action('wp_ajax_cpwebservice_validate_api_credentials', array(&$this,'woocommerce_cpwebservice_validate'));
            
            // Ajax Rates Log Display
            add_action('wp_ajax_cpwebservice_rates_log_display', array(&$this,'cpwebservice_rates_log_display'));
            
            // Ajax Shipment Log Display
            add_action('wp_ajax_cpwebservice_shipment_log_display', array(&$this,'cpwebservice_shipment_log_display'));
        }
       
        // Wire up tracking (admin, customer area and emails)
        add_action( 'init', array(&$this, 'cpwebservice_load_tracking'));
        
        // Action hook, run update on tracked orders.
        add_action('cpwebservice_tracking_schedule_update',  array(&$this,'cpwebservice_schedule_update') );
        
        // Reschedule hook
        add_action('cpwebservice_schedule_hook_action', array(&$this, 'cpwebservice_schedule_hook'));
        
        // Check for Plugin updates (only pulls current version info).
        add_action ('admin_init', array(&$this,'cpwebservice_load_update'));
        
        // Wire up plugins settings.
        add_action( 'admin_init', array(&$this,'cpwebservice_load_pluginsettings'));
       
        /** Activation hook - wireup schedule to update Tracking. */
        register_activation_hook( CPWEBSERVICE_PLUGIN_FILE, array(&$this,'cpwebservice_activation') );
        
        /** On deactivation, remove public function from the scheduled action hook. */
        register_deactivation_hook( CPWEBSERVICE_PLUGIN_FILE, array(&$this,'cpwebservice_deactivation') );
    }
    
    // action: woocommerce_shipping_init
    //Shipping Method Init public function
    public function shipping_init() {
        if (class_exists('WC_Shipping_Method') && !class_exists('woocommerce_cpwebservice')) {
            
            // Main Class Files
            require_once(CPWEBSERVICE_PLUGIN_PATH . '/framework/shippingmethod.php');
            require_once(CPWEBSERVICE_PLUGIN_PATH . '/models/woocommerce_cpwebservice.php');
            
            // Add Class to woocommerce_shipping_methods filter
            add_filter('woocommerce_shipping_methods', array(&$this, 'add_method') );
            
            // Add packing class.
            if (!class_exists('cpwebservice_pack')){
                require_once(CPWEBSERVICE_PLUGIN_PATH . '/framework/pack.php');
                require_once(CPWEBSERVICE_PLUGIN_PATH . '/framework/packservice.php');
            }
            // Add location class.
            if (!class_exists('cpwebservice_location')){
                require_once(CPWEBSERVICE_PLUGIN_PATH . '/framework/location.php');
            }
        }
        
    }
    
    // filter: woocommerce_shipping_methods
    // Add Class to woocommerce_shipping_methods filter
    public function add_method( $methods ) {
        $methods['woocommerce_cpwebservice'] = 'woocommerce_cpwebservice'; return $methods;
    }
    
    
    // Checkout order details
    public function orderdetails_init(){
        $this->cpwebservice_db_include();
        // Add orderdetails class.
        if (!class_exists('woocommerce_cpwebservice_orderdetails')){
            require_once(CPWEBSERVICE_PLUGIN_PATH . '/framework/orderdetails.php');
            require_once(CPWEBSERVICE_PLUGIN_PATH . '/models/woocommerce_cpwebservice_orderdetails.php');
        }
        $cp = new woocommerce_cpwebservice_orderdetails();
    }
    
    // Order Shipments
    public function shipments_init(){
        if ($this->get_resource('shipments_implemented')!==false) {
            $this->cpwebservice_db_include();
            // Add shipments class.
            if (!class_exists('woocommerce_cpwebservice_shipments')){
                require_once(CPWEBSERVICE_PLUGIN_PATH . '/framework/shipments.php');
                require_once(CPWEBSERVICE_PLUGIN_PATH . '/models/woocommerce_cpwebservice_shipments.php');
            }
            $cp = new woocommerce_cpwebservice_shipments();
        }
    }
    
    // Ajax Validate Action
    public function woocommerce_cpwebservice_validate() {
        // Load up woocommerce shipping stack.
        do_action('woocommerce_shipping_init');
        $shipping = new woocommerce_cpwebservice();
        $shipping->validate_api_credentials();
    }
    
    // Ajax Rates Log Display
    public function cpwebservice_rates_log_display() {
        // Load up woocommerce shipping stack.
        do_action('woocommerce_shipping_init');
        $shipping = new woocommerce_cpwebservice();
        $shipping->rates_log_display();
    }
    
    // Ajax Shipment Log Display
    public function cpwebservice_shipment_log_display() {
        // Load up woocommerce shipping stack.
        do_action('woocommerce_shipping_init');
        $shipments = new woocommerce_cpwebservice_shipments();
        $shipments->shipment_log_display();
    }
    
    
    // Wire up tracking
    public function cpwebservice_load_tracking() {
        // Tracking Details, Init, Include Class.
        if (!class_exists('woocommerce_cpwebservice_tracking')) {
            // Load Class
            require_once(CPWEBSERVICE_PLUGIN_PATH . '/framework/tracking.php');
            require_once(CPWEBSERVICE_PLUGIN_PATH . '/models/woocommerce_cpwebservice_tracking.php');
        }
        
        $tracking = new woocommerce_cpwebservice_tracking();
    }
    
    public function cpwebservice_schedule_update() {
        if (has_action('cpwebservice_scheduled_tracking')){
            do_action('cpwebservice_scheduled_tracking');
        }
        else {
            throw new Exception('Cron action does not exist. cpwebservice_scheduled_tracking');
        }
    }
    
    
    public function cpwebservice_load_pluginsettings() {
        $plugin = plugin_basename(CPWEBSERVICE_PLUGIN_FILE);
        add_filter((is_network_admin() ? 'network_admin_' : '')."plugin_action_links_$plugin", array(&$this, 'cpwebservice_settings_link'), 10, 4 );
    }
    // Add settings link on plugin page
    public function cpwebservice_settings_link($links, $plugin_file, $plugin_data, $context) {
        global $submenu;
        $settings_actions = array();
        if (isset($submenu['woocommerce']) && in_array( 'wc-settings', wp_list_pluck( $submenu['woocommerce'], 2 ) )){ // Woo 2.1
            $settings_actions['settings'] = '<a href="admin.php?page=wc-settings&tab=shipping&section=woocommerce_cpwebservice">'.esc_attr__('Settings','woocommerce-canadapost-webservice').'</a>';
        } else {
            $settings_actions['settings'] = '<a href="admin.php?page=woocommerce_settings&tab=shipping&section=woocommerce_cpwebservice">'.esc_attr__('Settings','woocommerce-canadapost-webservice').'</a>';
        }
        $settings_actions['support'] = sprintf( '<a href="%s" target="_blank">%s</a>', 'https://truemedia.ca/plugins/cpwebservice/support/', esc_attr__( 'Support', 'woocommerce-canadapost-webservice' ) );
        $settings_actions['review'] = sprintf( '<a href="%s" target="_blank">%s</a>', 'https://truemedia.ca/plugins/cpwebservice/reviews/', esc_attr__( 'Write a Review', 'woocommerce-canadapost-webservice' ) );
        
        return array_merge( $settings_actions, $links );
    }
    
    
    public function cpwebservice_activation() {
        $this->cpwebservice_schedule_hook('6:00pm');
        // Add a shipping class if none exist.
        $shipclasses = get_terms( 'product_shipping_class', array('hide_empty'=>0, 'fields'=>'ids') );
        if (empty($shipclasses) || count($shipclasses) == 0) {
            // Create a default shipping class 'Products'
            $term = wp_insert_term( __('Products', 'woocommerce-canadapost-webservice'), 'product_shipping_class', $args = array('slug' => 'products') );
        }
        // Check for Shipping Zones.
        if (class_exists('WC_Shipping_Zone')){
            // Used on Activation hook to update the main shipping zone to include our shipping method.
            $this->load_localisation();
            global $wpdb;
            $method_id = 'woocommerce_cpwebservice';
            $zone_id = 0; // Get "Rest of the World" zone.
            $exists = $wpdb->get_var( $wpdb->prepare( "SELECT method_id FROM {$wpdb->prefix}woocommerce_shipping_zone_methods WHERE zone_id = %d AND method_id = %s", $zone_id, $method_id ) );
            if (!$exists) {
                // Add to "Rest of the World" shipping zone.
                $zone = new WC_Shipping_Zone( $zone_id );
                if ($zone){
                    $zone->add_shipping_method( $method_id );
                }
            }
        }
        // Ensure db is setup
        $this->cpwebservice_db_include();
        $db = new woocommerce_cpwebservice_db();
        $version = get_option('cpwebservice_version', '1.0');
        $db->setup($version);
    }
    
    public function cpwebservice_schedule_hook($timeofday) {
        wp_clear_scheduled_hook( 'cpwebservice_tracking_schedule_update' );
        // Daily schedule, local time.
        $wp_timezone = get_option( 'timezone_string' );
        $timeofday = in_array($timeofday, array('9:00pm','6:00pm','1:00pm','9:00am')) ? $timeofday : '6:00pm';
        $date = new DateTime("today $timeofday", !empty($wp_timezone) ? new DateTimeZone($wp_timezone) : null);
        // Schedule hook
        wp_schedule_event( $date->getTimestamp() , 'daily', 'cpwebservice_tracking_schedule_update' );
    }

    public function cpwebservice_deactivation() {
        wp_clear_scheduled_hook( 'cpwebservice_tracking_schedule_update' );
    }
    
    
    public function cpwebservice_init_require_postal_code() {
        add_filter('woocommerce_shipping_calculator_enable_postcode', array(&$this, 'cpwebservice_require_postal_code'));
    }
    public function cpwebservice_require_postal_code($enabled) {
        if ($enabled && get_option('cpwebservice_require_postal', false) == 'yes'){
            wp_enqueue_script('canadapost-require-postalcode', plugins_url('lib/require-postalcode.js', CPWEBSERVICE_PLUGIN_FILE), array('jquery'), '1.0', true);
            echo '<div id="calc_shipping_postcode_required" class="hidden woocommerce-info" style="display:none">'.esc_html__('Zip / Postal Code is required to calculate shipping', 'woocommerce-canadapost-webservice').'</div>';
        }
        return $enabled;
    }
    
    public function cpwebservice_shipping_method_label($label) {
        if (get_option('woocommerce_shipping_method_format') != 'select' && strpos($label, '<span class="shipping-delivery">')===false) {
            // Update Label to have a <span> around the (Delivered by)
            $label = preg_replace('/(\(.+\))/','<span class="shipping-delivery">$1</span>',$label);
        }
        return $label;
    }
    
    public function load_product_options() {
        // Product shipping options
        if (is_admin() && !class_exists('woocommerce_cpwebservice_products')) {
            // Load Class
            require_once(CPWEBSERVICE_PLUGIN_PATH . '/framework/products.php');
            require_once(CPWEBSERVICE_PLUGIN_PATH . '/models/woocommerce_cpwebservice_products.php');
        }
        
        $cp = new woocommerce_cpwebservice_products();
        $cp->method_title = $this->get_resource('method_title');
    } 
    
    // Check for Plugin updates (only pulls current version info).
    public function cpwebservice_load_update(){
        // Check for Plugin updates (only pulls current version info).
        if (is_admin() && !class_exists('woocommerce_cpwebservice_update')) {
            require_once(CPWEBSERVICE_PLUGIN_PATH . '/framework/update.php');
            require_once(CPWEBSERVICE_PLUGIN_PATH . '/models/woocommerce_cpwebservice_update.php');
            $update = new woocommerce_cpwebservice_update( 'https://truemedia.ca/plugins/cpwebservice/version/', CPWEBSERVICE_PLUGIN_FILE, array('version' => CPWEBSERVICE_VERSION, 'upgrade_url'=>'https://truemedia.ca/envato-server', 'product'=>'cpwebservice'));
        }
    }

    public function cpwebservice_db_include() {
        if (!class_exists('woocommerce_cpwebservice_db')){
            require_once(CPWEBSERVICE_PLUGIN_PATH . '/framework/db.php');
            require_once(CPWEBSERVICE_PLUGIN_PATH . '/models/woocommerce_cpwebservice_db.php');
        }
    }

    public function cpwebservice_db() {
        $this->cpwebservice_db_include();
        // Check for Database updates.
        $version = get_option('cpwebservice_version', '1.0');
        if( version_compare( $version, CPWEBSERVICE_VERSION, '<' ) ){
            $db = new woocommerce_cpwebservice_db();
            // Setup / Run Update.
            $db->setup($version);
        }
    }
    
    
    /**
     * Load Localisation
     */
    public function load_localisation() {
        load_plugin_textdomain( 'woocommerce-canadapost-webservice', false, dirname(plugin_basename(CPWEBSERVICE_PLUGIN_FILE)). '/languages' );
        // Resources
        require_once(CPWEBSERVICE_PLUGIN_PATH . '/framework/resources.php');
        require_once(CPWEBSERVICE_PLUGIN_PATH . '/models/woocommerce_cpwebservice_resources.php');
    }
    
    
}