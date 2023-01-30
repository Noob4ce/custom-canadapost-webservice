<?php
/*
 Plugin Load class
woocommerce_cpwebservice_plugin.php

Copyright (c) 2013-2022 Jamez Picard

*/
class woocommerce_cpwebservice_plugin extends cpwebservice_plugin
{
    function get_resource($id) {
        return cpwebservice_r::resource($id);
    }
}