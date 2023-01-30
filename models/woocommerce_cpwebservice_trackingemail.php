<?php
/*
 Tracking Email class
woocommerce_cpwebservice_trackingemail.php

Copyright (c) 2013-2022 Jamez Picard

*/
class woocommerce_cpwebservice_trackingemail extends cpwebservice_trackingemail
{
    function get_resource($id) {
        return cpwebservice_r::resource($id);
    }
}