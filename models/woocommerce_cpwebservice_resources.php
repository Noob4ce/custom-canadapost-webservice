<?php
/*
Resources class
woocommerce_cpwebservice_resources.php

Copyright (c) 2013-2022 Jamez Picard
*/
class cpwebservice_r extends cpwebservice_resources
{
    public static function resource($id) {
        switch($id)
        {
            // Main
            case 'method_title': return __('Canada Post', 'woocommerce-canadapost-webservice');
            case 'method_description' : return __('Shipping method for Canada Post. Provides rates, tracking and shipment labels.', 'woocommerce-canadapost-webservice');
            case 'method_logo_url': return 'img/canada-post.png';
            case 'method_logo_icon': return 'img/canada-post.png';
            case 'shipment_icon': return 'img/ship_canadapost.png';
            case 'version_url' : return 'https://truemedia.ca/';
            case 'version' : return CPWEBSERVICE_VERSION;
            // Default
            case 'services_default_unselected' : return array('DOM.XP.CERT','DOM.LIB');
            // Label Descriptions
            case 'parcel_services' : return __('(ie. if Regular Parcel and Expedited Parcel are same cost, keep Expedited Parcel Service.)', 'woocommerce-canadapost-webservice');
            case 'guaranteed_services' : return __('(ie. Regular Parcel will not display, only Xpresspost, Priority, etc)', 'woocommerce-canadapost-webservice');
            case 'lettermail_default' : return __('Canada Post Lettermail', 'woocommerce-canadapost-webservice');
            case 'margin_currency': return __('Canada Post rates are in CAD.  In order to convert to USD, put the exchange rate "to USD" in the Exchange rate.', 'woocommerce-canadapost-webservice');
            case 'contract_description': return '';
            case 'volumetric_weight_recommend' : return sprintf(__('(Recommended by %s)', 'woocommerce-canadapost-webservice'), self::resource('method_title') );
            // Rate Lookups
            case 'max_cp_box': return array('length'=>200 , 'width'=> 200, 'height'=>200, 'girth'=> 300, 'weight'=> 30);
            case 'volumetric_weight_default': return true;
            // Shipments
            case 'method_website_orders_url': return 'https://www.canadapost.ca/dash/en';
            case 'method_website_account_url': return 'https://www.canadapost.ca/cpotools/apps/creditcard';
            case 'shipment_country' : return 'CA';
            case 'shipment_country_label' : return 'Canada';
            case 'sender_shipment_countries' : return array('CA'=> 'Canada');
            case 'postalcode_warning': return __('Warning: Postal Code is invalid. Required to be a valid Canadian postal code.', 'woocommerce-canadapost-webservice');
            case 'hscode_search_url' : return 'https://www.canadapost-postescanada.ca/information/app/wtz/business/findHsCode?execution=e2s1';
            case 'dropoff_search_url': return 'https://www.canadapost-postescanada.ca/information/app/fdl/business/findDepositLocation?execution=e3s1';
            case 'shipment_payment_onfile' : return __('Credit Card (on file at your Canada Post Account)', 'woocommerce-canadapost-webservice');
            case 'shipment_payment_onaccount': return __('Account (Your account must be in good standing at Canada Post)', 'woocommerce-canadapost-webservice');
            case 'shipment_emailupdates' : return  __('Email notifications (Sent by Canada Post)', 'woocommerce-canadapost-webservice');
            // Display Options
            case 'default_units': return 'cm';
            case 'default_unitweight': return 'kg';
            case 'origin_postal_placeholder' : return 'A1A1A1';
            case 'tracking_dateonly' : return true;
            case 'shipments_implemented' : return true;
        }
        return '';
    }
}