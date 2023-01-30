<?php
/*
 Database Repo class
 cpwebservice_db.php
 
 Copyright (c) 2017-2021 Jamez Picard
 
 */
abstract class cpwebservice_db
{
    public $collate;
    public $prev_version;
    public function setup($version) {
        global $wpdb;
        $this->prev_version = !empty($version) ? $version : "1.0";
        // Create sql tables as needed.
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $this->get_collate();
        $this->sql_tracking();
        $this->sql_shipments();
        //if ($this->get_resource('shipments_implemented')){
          //  Future development
          //  $this->sql_lists();
          //  $this->sql_services();
        //}
        // Done.
        if (!empty($wpdb->last_error)) {
            // Provide message to admin user.
            $msg = array('error'=>$wpdb->last_error, 'type'=>'db_upgrade');
            set_transient('cpwebservice_db_msg', $msg, 1 * MINUTE_IN_SECONDS);
        }
        update_option('cpwebservice_version', CPWEBSERVICE_VERSION);
    }

    public function sql_tracking() {
        global $wpdb;
        $sql = "CREATE TABLE {$wpdb->prefix}cpwebservice_tracking (
            id bigint UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id bigint UNSIGNED NOT NULL,
            pin varchar(240) NOT NULL,
            meta longtext NULL,
            events longtext NULL,
            dateupdated datetime NOT NULL,
            dateshipped datetime NULL,
            datedelivered datetime NULL,
            priority int(11) NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY order_id (order_id,pin)
        ) {$this->collate};";
        dbDelta( $sql );
    }

    public function sql_shipments() {
        global $wpdb;
        $sql = "CREATE TABLE {$wpdb->prefix}cpwebservice_shipments (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id bigint(20) UNSIGNED NOT NULL,
            list_id bigint(20) NULL,
            package_index int(11) NULL,
            package longtext NULL,
            shipment longtext NULL,
            rates text NULL,
            service_code varchar(50) NULL,
            service varchar(100) NULL,
            origin_postalcode varchar(50) NULL,
            sender_address_index int(11) UNSIGNED NULL,
            sender_address varchar(1500) NULL,
            destination_country varchar(500) NULL,
            destination_postalcode varchar(50) NULL,
            dateupdated datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY order_id (order_id,package_index),
            KEY list_id (list_id)
        ) {$this->collate};";
        dbDelta( $sql );
    }

    public function sql_services() {
        global $wpdb;
        $sql = "CREATE TABLE {$wpdb->prefix}cpwebservice_services (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id bigint(20) UNSIGNED NOT NULL,
            service_type varchar(20) NOT NULL,
            meta longtext NULL,
            dateupdated datetime NOT NULL,
            datecompleted datetime NULL,
            PRIMARY KEY  (id),
            KEY order_id (order_id)
        ) {$this->collate};";
        dbDelta( $sql );
    }

    public function sql_lists() {
        global $wpdb;
        $sql = "CREATE TABLE {$wpdb->prefix}cpwebservice_shipment_lists (
            list_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id bigint(20) UNSIGNED NOT NULL,
            status varchar(20) NOT NULL,
            dateupdated datetime NOT NULL,
            datecompleted datetime NULL,
            meta longtext NULL,
            PRIMARY KEY  (list_id),
            KEY order_id (order_id,status)
        ) {$this->collate};";
        dbDelta( $sql );
    }

    public function get_collate() {
        global $wpdb;
        if ($wpdb->has_cap( 'utf8mb4_520' )){
           $this->collate = 'DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci';
        } elseif ($wpdb->has_cap( 'utf8mb4' )){
            $this->collate = 'DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        } else {
            $this->collate = 'DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci';
        }
        return $this->collate;
    }
    
    public function no_errors() {
        global $wpdb;
        return empty($wpdb->last_error);
    }

    /*
	 * Return resources
	 */
	abstract function get_resource($id);
    
    /*
     * Date format
    */
    public function db_datetime($datestring){
        if (!empty($datestring)){
            // Converts to mysql date string format
            return (new DateTime($datestring))->format('Y-m-d H:i:s');
        }
        // else
        return '';
    }
    
    /**
     * Package Shipment Tracking Data
    */
    public function tracking_get($order_id){
        if (!empty($order_id)){
            global $wpdb;
            return $wpdb->get_results($wpdb->prepare("SELECT id, order_id, pin, meta, events, dateupdated, dateshipped, datedelivered FROM {$wpdb->prefix}cpwebservice_tracking WHERE order_id = %d ORDER BY id", $order_id));
        }
        return array();
    }
    // Returns all pins on order
    public function tracking_get_pins($order_id){
        if (!empty($order_id)){
            global $wpdb;
            return $wpdb->get_col($wpdb->prepare("SELECT pin FROM {$wpdb->prefix}cpwebservice_tracking WHERE order_id = %d ORDER BY id", $order_id));
        }
        return array();
    }

    public function tracking_get_details($order_id, $pin){
        if (!empty($order_id) && !empty($pin)){
            global $wpdb;
            return $wpdb->get_results($wpdb->prepare("SELECT id, order_id, pin, meta, dateupdated, events, dateshipped, datedelivered FROM {$wpdb->prefix}cpwebservice_tracking WHERE order_id = %d AND pin = %s", $order_id, $pin));
        }
        return array();
    }

    public function tracking_get_multi($order_ids){
        if (!empty($order_ids) && is_array($order_ids)){
            global $wpdb;
            $order_ids_placeholders = implode(',', array_fill(0, count($order_ids), '%d'));
            return $wpdb->get_results($wpdb->prepare("SELECT id, order_id, pin, meta, dateupdated, dateshipped, datedelivered FROM {$wpdb->prefix}cpwebservice_tracking WHERE order_id IN ($order_ids_placeholders) ORDER BY id", $order_ids));
        }
        return array();
    }

    public function tracking_save($order_id, $pin, $meta, $events, $dateshipped, $datedelivered){
        global $wpdb;
        $dateshipped = $this->db_datetime($dateshipped);
        $datedelivered = $this->db_datetime($datedelivered);
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id from {$wpdb->prefix}cpwebservice_tracking WHERE order_id = %d AND pin = %s", $order_id, $pin));
        if ($exists){
            // Update
            $result = $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}cpwebservice_tracking SET meta = %s, events = %s, dateupdated = now(), dateshipped = nullif(%s,''), datedelivered = nullif(%s,'') where order_id = %d AND pin = %s", $meta, $events, $dateshipped, $datedelivered, $order_id, $pin));
        } else {
            // Insert
            $result = $wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->prefix}cpwebservice_tracking (order_id, pin, meta, events, dateupdated, dateshipped, datedelivered) VALUES (%d, %s, %s, %s, now(), nullif(%s,''), nullif(%s,''))", $order_id, $pin, $meta, $events, $dateshipped, $datedelivered));
        }
    }

    public function tracking_remove($order_id, $pin){
        global $wpdb;
        // Remove 
        $result = $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}cpwebservice_tracking where order_id = %d AND pin = %s", $order_id, $pin));
        return $result ? true:false;
    }
   
    // Legacy Package Shipment Tracking data 
    public function tracking_get_legacy($order_id){
        $pins = get_post_meta( $order_id, '_cpwebservice_tracking', true);
        return $pins;
    }
    public function tracking_get_legacy_meta($order_id){
        $meta = get_post_meta( $order_id, '_cpwebservice_tracking_meta', true);
        return $meta;
    }
    public function tracking_remove_legacy($order_id){
        delete_post_meta($order_id, '_cpwebservice_tracking');
        delete_post_meta($order_id, '_cpwebservice_tracking_meta');
    }
    public function tracking_get_all_legacy_order_ids(){
        global $wpdb;
        $order_ids = $data = $wpdb->get_col("SELECT DISTINCT post_id FROM {$wpdb->prefix}postmeta WHERE (meta_key = '_cpwebservice_tracking' OR meta_key = '_cpwebservice_tracking_meta') ORDER BY post_id");
        return $order_ids;
    }
    /* End Package Shipment Tracking Data  */
    

    /*
    * Order Shipments
    */

    public function shipments_get($order_id){
        if (!empty($order_id)){
            global $wpdb;
            $shipping_info = $wpdb->get_results($wpdb->prepare("SELECT id, order_id, package_index, list_id, package, shipment, rates, service_code, service, origin_postalcode, sender_address_index, sender_address, destination_country, destination_postalcode, dateupdated
                FROM {$wpdb->prefix}cpwebservice_shipments WHERE order_id = %d ORDER BY package_index", $order_id), ARRAY_A);
            
            return $this->shipments_decode($shipping_info);
        }
        return array();
    }

    public function shipment_get($order_id, $package_index){
        if (!empty($order_id)){
            global $wpdb;
            $shipping_info = $wpdb->get_results($wpdb->prepare("SELECT id, order_id, package_index, list_id, package, shipment, rates, service_code, service, origin_postalcode, sender_address_index, sender_address, destination_country, destination_postalcode, dateupdated
                FROM {$wpdb->prefix}cpwebservice_shipments WHERE order_id = %d and package_index = %d", $order_id, $package_index), ARRAY_A);
            
            $shipping = $this->shipments_decode($shipping_info);
            return !empty($shipping) && isset($shipping[0]) ? $shipping[0] : array();
        }
        return array();
    }

    public function shipments_exist($order_id){
        global $wpdb;
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id from {$wpdb->prefix}cpwebservice_shipments WHERE order_id = %d LIMIT 1", $order_id));
        return !empty($exists);
    }

    private function shipments_decode($shipping_info){
        foreach($shipping_info as $i => $shipping){
            if (!empty($shipping['sender_address'])){
                $shipping_info[$i]['sender_address'] = json_decode($shipping['sender_address'], true);
            }
            if (!empty($shipping['rates'])){
                $shipping_info[$i]['rates'] = json_decode($shipping['rates']);
            }
            if (!empty($shipping['package'])){
                $shipping_info[$i]['package'] = json_decode($shipping['package'], true);
            }
            if (!empty($shipping['shipment'])){
                $shipping_info[$i]['shipment'] = json_decode($shipping['shipment'], true);
                // objects
                if (!empty($shipping_info[$i]['shipment']['label'])){
                    $shipping_info[$i]['shipment']['label'] = json_decode(json_encode($shipping_info[$i]['shipment']['label']), false);
                }
                if (!empty($shipping_info[$i]['shipment']['refund'])){
                    $shipping_info[$i]['shipment']['refund'] = json_decode(json_encode($shipping_info[$i]['shipment']['refund']), false);
                }
            }
        }
        return $shipping_info;
    }

    public function shipment_save($order_id, $shipping, $package_index) {
        if (!empty($shipping) && is_array($shipping)){
            global $wpdb;
            if (!empty($shipping['sender_address'])){
                $shipping['sender_address'] = json_encode($shipping['sender_address']);
            }
            if (!empty($shipping['rates'])){
                $shipping['rates'] = json_encode($shipping['rates']);
            }
            if (!empty($shipping['package'])){
                $shipping['package'] = json_encode($shipping['package']);
            }
            if (!empty($shipping['shipment'])){
                $shipping['shipment'] = json_encode($shipping['shipment']);
            }
            $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}cpwebservice_shipments WHERE order_id = %d AND package_index = %d", $order_id, $package_index));
            if ($exists){
                // Update
                $result = $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}cpwebservice_shipments SET 
                    list_id=nullif(%d,0), package=%s, shipment=nullif(%s,''), rates=nullif(%s,''), service_code=nullif(%s,''), service=nullif(%s,''),
                    origin_postalcode=nullif(%s,''), sender_address_index=%d, sender_address=%s, destination_country=%s, destination_postalcode=%s, dateupdated = now() WHERE order_id = %d AND package_index = %d", 
                    $shipping['list_id'], $shipping['package'], $shipping['shipment'], $shipping['rates'], $shipping['service_code'], $shipping['service'], $shipping['origin_postalcode'], $shipping['sender_address_index'], $shipping['sender_address'], $shipping['destination_country'], $shipping['destination_postalcode'],
                    $order_id, $package_index));
            } else {
                // Insert
                $result = $wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->prefix}cpwebservice_shipments (order_id,  package_index, list_id, package, shipment, 
                rates, service_code, service, origin_postalcode, sender_address_index, sender_address, destination_country, destination_postalcode, dateupdated) 
                VALUES (%d, %d, nullif(%d,0), %s, nullif(%s,''), nullif(%s,''), nullif(%s,''), nullif(%s,''), nullif(%s,''), %d, %s, %s, %s, NOW())", 
                $order_id, $package_index, $shipping['list_id'], $shipping['package'], $shipping['shipment'], $shipping['rates'], $shipping['service_code'], $shipping['service'], $shipping['origin_postalcode'], $shipping['sender_address_index'], $shipping['sender_address'], $shipping['destination_country'], $shipping['destination_postalcode']));
            }
            // Done
        }
    }

    public function shipment_remove($order_id, $package_index) {
        if (!empty($order_id)){
            // Delete
            global $wpdb;
            $result = $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}cpwebservice_shipments WHERE order_id = %d AND package_index = %d", $order_id, $package_index));
        }
    }

    public function shipments_get_legacy($order_id){
        $shipping_info  = get_post_meta( $order_id , '_cpwebservice_shipping_info', true);
        $shipping_info = !empty($shipping_info) && is_array($shipping_info) ? $this->shipments_convert_legacy($shipping_info, $order_id) : array();
        return $shipping_info;
    }
    public function shipments_remove_legacy($order_id){
        delete_post_meta($order_id, '_cpwebservice_shipping_info');
    }
    public function shipments_exist_legacy($order_id){
        global $wpdb;
        $exists = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM {$wpdb->prefix}postmeta WHERE meta_key = '_cpwebservice_shipping_info' and post_id = %d", $order_id));
        return !empty($exists);
    }

    public function shipments_get_all_legacy_order_ids(){
        global $wpdb;
        $order_ids = $data = $wpdb->get_col("SELECT DISTINCT post_id FROM {$wpdb->prefix}postmeta WHERE meta_key = '_cpwebservice_shipping_info' ORDER BY post_id");
        return $order_ids;
    }

    public function shipments_convert_legacy($shipping_info, $order_id){
        $shipments = array();
        foreach($shipping_info['packages'] as $i => $package){
            $shipments[$i] = array('order_id'=>$order_id, 'package_index' =>$i, 'list_id'=> '','shipment'=>'','service_code'=>'', 'service'=>'', 'sender_address_index'=>0,'sender_address'=>'','destination_country'=>'','destination_postalcode'=>'');
            $shipments[$i]['origin_postalcode'] = !empty($shipping_info['origin']) ? $shipping_info['origin'] : '';
            $shipments[$i]['package'] = array_intersect_key($package, array_flip(array('length', 'width', 'height','weight','cubic','products','actual_weight')));
            if (isset($shipping_info['rates'])){
                if (is_object($shipping_info['rates'])){
                    $shipping_info['rates'] = (object)(array)$shipping_info['rates'];
                    if (empty($shipping_info['rates']->service_code) && isset($shipping_info['rates']->id) && stristr($shipping_info['rates']->id, ':')){
                        $ids = explode(':', $shipping_info['rates']->id);
                        if (count($ids) > 1){ // ex. woocommerce_service:USA.SP.AIR or woocommerce_service:USA.SP.AIR
                            $shipping_info['rates']->service_code = $ids[count($ids)-1];
                            if (empty($shipping_info['rates']->service) && !empty($shipping_info['rates']->label)) {
                                $shipping_info['rates']->service = $shipping_info['rates']->label;
                            }
                            if (empty($shipping_info['rates']->price) && !empty($shipping_info['rates']->cost)) {
                                $shipping_info['rates']->price = $shipping_info['rates']->cost;
                            }
                        }
                    }
                    $shipments[$i]['rates'] = array($shipping_info['rates']);
                    $shipments[$i]['service_code'] = isset($shipping_info['rates']->service_code) ? $shipping_info['rates']->service_code : '';
                    $shipments[$i]['service'] = isset($shipping_info['rates']->service) ? $shipping_info['rates']->service : '';
                }
                elseif(is_array($shipping_info['rates'])){
                    $shipments[$i]['rates'] = $shipping_info['rates'];
                }
            }
            if (empty($shipments[$i]['rates']) && !empty($package['rate']) && is_array($package['rate'])){
                $shipments[$i]['rates'] = $package['rate'];
                if (!empty($shipments[$i]['service_code'])){
                    foreach($package_index['rate'] as $rate){
                        if ($rate->service_code == $shipments[$i]['service_code'] && !empty($rate->service)){
                            $shipments[$i]['service'] = $rate->service;
                            break;
                        }
                    }
                }
            }
            if (isset($shipping_info['shipment']) && is_array($shipping_info['shipment']) && !empty($shipping_info['shipment'][$i]))
            {
               $shipments[$i]['shipment'] = $shipping_info['shipment'][$i];
               $shipments[$i]['sender_address_index'] = isset($shipping_info['shipment'][$i]['sender_address_index']) ? $shipping_info['shipment'][$i]['sender_address_index'] : 0;               
               $shipments[$i]['service_code'] = $shipments[$i]['shipment']['method_id'];
               $shipments[$i]['service'] = isset($shipments[$i]['shipment']['method_name']) ? $shipments[$i]['shipment']['method_name'] : '';
               if (!empty($shipments[$i]['shipment']['label'])){
                    if (!empty($shipments[$i]['shipment']['label']->destination_country)){
                        $shipments[$i]['destination_country'] = $shipments[$i]['shipment']['label']->destination_country;
                    }
                    if (!empty($shipments[$i]['shipment']['label']->destination_postal)){
                        $shipments[$i]['destination_postalcode'] = $shipments[$i]['shipment']['label']->destination_postal;
                    }
                }
            }

        } // end foreach
        return $shipments;
    }

    /*
    * End Order Shipments
    */
}