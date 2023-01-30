<?php
/*
Order Details class
woocommerce_cpwebservice_orderdetails.php

Copyright (c) 2013-2022 Jamez Picard

*/
class woocommerce_cpwebservice_orderdetails extends cpwebservice_orderdetails
{
    function get_resource($id) {
        return cpwebservice_r::resource($id);
    }
}