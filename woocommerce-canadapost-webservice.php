<?php
/*
Plugin Name: WooCommerce Canada Post Webservice Method
Plugin URI: https://truemedia.ca/plugins/cpwebservice/
Description: Extends WooCommerce with Shipping Rates, Shipment Labels and Tracking from Canada Post via Webservices
Version: 1.7.10
Author: Jamez Picard
Author URI: https://truemedia.ca/
Text Domain: woocommerce-canadapost-webservice
Domain Path: /languages
Requires at least: 3.1
Tested up to: 6.1.0
WC requires at least: 2.4.0
WC tested up to: 7.0.1

Copyright (c) 2013-2022 Jamez Picard TrueMedia

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED 
TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL 
THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF 
CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS 
IN THE SOFTWARE.
*/
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

//Check if WooCommerce is active
if (in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ||
   (is_multisite() && array_key_exists( 'woocommerce/woocommerce.php', get_site_option( 'active_sitewide_plugins') ))) {

define('CPWEBSERVICE_VERSION', '1.7.10');

// Plugin Path
define('CPWEBSERVICE_PLUGIN_PATH', dirname(__FILE__));
define('CPWEBSERVICE_PLUGIN_FILE', __FILE__);

require_once(CPWEBSERVICE_PLUGIN_PATH . '/framework/plugin.php');
require_once(CPWEBSERVICE_PLUGIN_PATH . '/models/woocommerce_cpwebservice_plugin.php');
$canadapost = new woocommerce_cpwebservice_plugin();


} // End check if WooCommerce is active

