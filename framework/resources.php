<?php
/*
 Resource class
 cpwebservice_resources.php

 Copyright (c) 2013-2022 Jamez Picard

 */
abstract class cpwebservice_resources
{
    
    public static function resource($id) {
        switch($id)
        {
            // Main
            case 'method_title': return 'TrueMedia';
            case 'method_logo_url': return 'img/cpwebservice.png';
    
            // Label Descriptions
            case 'parcel_services' : return __('(ie. if Ground and Express are same cost, keep the Express Service.)', 'woocommerce-canadapost-webservice');
            case 'lettermail_default' : return __('Lettermail', 'woocommerce-canadapost-webservice');
            // Default
            case 'services_disabled_by_default' : return array();
            
            // Shipments
            case 'shipment_country' : return 'CA';
            case 'shipment_country_label' : return 'Canada';
            // Sender Shipment Countries (Can be used as Domestic).
            case 'sender_shipment_countries' : return array('US' => 'United States', 'CA'=> 'Canada');
            case 'postalcode_warning': return __('Warning: Postal Code is invalid.', 'woocommerce-canadapost-webservice');
            case 'hscode_search_url' : return 'https://www.canadapost.ca/cpotools/apps/wtz/personal/findHsCode';
        }
        return '';
    }
    
    
    /*
     * Utility method to safely round.  Even if the locale has a problem decimal separator ',' it'll be fine.
     */
    
    public static function round_decimal($number,$precision=0)
    {
        return str_replace(',','.', round($number, $precision));
    }
    
    // END Section Static Conversions
    
    // Begin Convenient Display Functions
    public static function display_unit($cm, $display_units_option){
        return self::round_decimal(self::convert($cm,'cm', $display_units_option),3); // ex. $display_units_option == 'in'.
    }
    
    public static function display_unit_cubed($cm3, $display_units_option){
        return self::round_decimal(self::convert($cm3,'cm3', $display_units_option . '3'),3); // ex. $display_units_option . '3' == 'in3'.
    }
    
    // Display Weight
    public static function display_weight($kg, $display_weights_option){
        return self::round_decimal(self::convert($kg,'kg', $display_weights_option),3);
    }
    
    // Save Size (to cm)
    // Returns cm
    public static function save_unit($size, $display_units_option){
        return self::round_decimal(self::convert($size, $display_units_option, 'cm'),5); // ex. $display_units_option == 'in'.
    }
    
    // Save weight (to kg)
    // Returns kg.
    public static function save_weight($weight, $display_weights_option){
        return self::round_decimal(self::convert($weight, $display_weights_option, 'kg'),5); // ex. $display_weights_option== 'kg'.
    }
    
    // END Section Size/Weight Display Options.
    
    // Begin Conversions
    
    private static $conv = array(
        // Length
        "cm" => array("base" => "cm", "conv" => 1), //cm - base unit for length
        "m" => array("base" => "cm", "conv" =>  100), //meter
        "mm" => array("base" => "cm", "conv" => 0.1), //millimeter
        "dm" => array("base" => "cm", "conv" => 10), //decimeter
        "in" => array("base" => "cm", "conv" => 2.54), //inch
        "ft" => array("base" => "cm", "conv" => 30.48), //foot
        "yd" => array("base" => "cm", "conv" => 91.44), //yd
        // Weight/Mass
        "kg" => array("base" => "kg", "conv" => 1), //kilogram - base
        "g" => array("base" => "kg", "conv" => 0.001), //gram
        "mg" => array("base" => "kg", "conv" => 0.000001), //miligram
        "lb" => array("base" => "kg", "conv" => 0.453592), //pound
        "lbs" => array("base" => "kg", "conv" => 0.453592), //pound(alias)
        "oz" => array("base" => "kg", "conv" => 0.0283495), //ounce
        // Volume
        "cm3" => array("base" => "cm3", "conv" => 1), //cubic centimeter
        "m3" => array("base" => "cm3", "conv" => 1000000), //cubic meters
        "mm3" => array("base" => "cm3", "conv" => 0.0001), //cubic millimeters
        "in3" => array("base" => "cm3", "conv" => 16.387064), //cubic inches
        "ft3" => array("base" => "cm3", "conv" => 28316.84659), //cubic feet
        "yd3" => array("base" => "cm3", "conv" => 764554.858), //cubic yds
    );
    private static $base_units = array('cm', 'kg', 'cm3');
    
    public static function convert($value, $value_unit, $to_unit) {
        if ($value_unit == $to_unit){
            // It's the same.
            return floatval($value);
        }
        $value_base_unit = in_array($value_unit, self::$base_units);
        $to_base_unit = in_array($to_unit, self::$base_units);
        
        // Use Conversion Array.
        if ($to_base_unit && isset(self::$conv[$value_unit])) {
            // Do conversion
            return floatval($value) * self::$conv[$value_unit]['conv'];
        }
        if ($value_base_unit && isset(self::$conv[$to_unit])) {
            // Do conversion.
            return floatval($value) / self::$conv[$to_unit]['conv'];
        }
        // default.
        return floatval($value);
    }
    // End Conversions
    
}