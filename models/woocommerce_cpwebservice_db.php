<?php
/*
 Plugin Load class
woocommerce_cpwebservice_db.php

Copyright (c) 2018-2022 Jamez Picard

*/
class woocommerce_cpwebservice_db extends cpwebservice_db
{
    function get_resource($id) {
        return cpwebservice_r::resource($id);
    }
}