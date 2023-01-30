<?php
/*
 Packing Service class
cpwebservice_packservice.php

Requires Pack class for box packing
(pack.php)

Copyright (c) 2013-2022 Jamez Picard

*/
class cpwebservice_packservice
{
    
    /* settings/setup */
    private $settings = array();
    private $boxes    = array();
    private $packages = array();
    private $containers=array();
    // Advanced Packing
    private $pack      = null;
    /* debug log */
    public $log      = array();
    public $log_enable = false;
    
    /**
     * __construct function.
     *
     * @access public
     * @return cpwebservice_packservice
     */
    function __construct($settings = array()) {
        $this->init($settings);
    }
    
    /*
     * Usage:
     * $packservice = new cpwebservice_packservice($settings);
     * $containers = $packservice->productpack($products, $boxes, $containers, $packing_mode);
     */
    
    /*
     * Init/Construct
     */
    private function init($settings = array()) {
        // Set defaults/settings
        $default_options = array('boxes_switch'=>true, 'pack_with_boxes' => true, 'max_cp_box'=>array('length'=>200 , 'width'=> 200, 'height'=>200, 'girth'=> 300, 'weight'=> 30), 'largest_as_max_box'=>false);
        $this->settings	 =	(object) array_merge($default_options, $settings); // ensure all keys exist, as defined in default_options.
    }
    
    
    /**
     * Box Packing for products
     * @param array $products
     * @param array $boxes
     * @param string $packing_mode 'boxpack3d' or 'volumetric'
     * @return array $containers
     */
    public function productpack($products, $boxes, $containers = array(), $packing_mode = 'boxpack3d'){ 
        $this->products   = $products;
        $this->containers = $containers;
        $this->pack = new cpwebservice_pack();
        // Set up Boxes
        $this->boxes = $boxes;
        $this->init_max_box();
        
        if (!empty($this->products)){
            // Rotate packages for best packing.
           $this->pack->rotate_boxes($this->products);
            
            // Optimize Box Order
            if ($this->settings->pack_with_boxes){
                                
                // rotate boxes so smallest dimension is height.
                $this->pack->rotate_boxes($this->boxes);
                $this->optimize_sort_boxes($this->products);
            }
            
            // Advanced 3D Box packing        
            if ($packing_mode == 'boxpack3d'){
                $this->containers = $this->boxpack($this->products, $this->boxes, $this->containers);
            }
            // Volumetric Packing
            else if ($packing_mode == 'volumetric'){
                $this->containers = $this->volumepack($this->products, $this->boxes, $this->containers);
            }
        }
        
        return $this->containers;
        
    }
    
    
   
    
    
    /**
     * Uses Box Packing for products
     * @param array $products
     * @param array $boxes
     * @return array $containers
     */
    public function boxpack($products, $boxes, $containers = array()){
        // Set up Boxes
        $this->boxes = $boxes;
        if ($this->log_enable) { $this->pack->enable_debug(); }
        $pack_with_boxes = $this->settings->pack_with_boxes;
        $box_count = count($this->boxes);
        $packrotate = $pack_with_boxes ? new cpwebservice_packrotate() : null;
        $box_switch = false;
        
        // Loop over products to pack.
        while (!empty($products) && is_array($products)){
             
            // Optimistic pack - no box containers defined.
            if (!$this->settings->pack_with_boxes || $box_switch){
                $products_topack = count($products);
                // $box_switch = false; // switch back to box mode if that's why it's here.
                // Pack and Check Container
                $this->pack->pack($products, null, $this->settings->max_cp_box);
                $packed_boxes = $this->pack->get_packed_boxes();
                if (is_array($packed_boxes) && count($packed_boxes) > 0){
                    // Get Container (Includes Products packed)
                    $container = $this->pack->get_container_complete();
                    // Add to containers array.
                    $containers[] = $container;
        
                    // Loop to pack remaining products
                    $products = $this->pack->get_remaining_boxes();
                }// endif
                 
                if ($products_topack == count($products) && $products_topack > 0){
                    // No products were packed this loop. (probably too big or too heavy, hitting the max)  Have to finish this while loop.
                    // Best thing to do is pack the largest product separately. Products were sorted as largest to smallest, so remove first product.
                    // Remove (array_shift) this as it was just packed individually.
                    $containers[] = $this->to_container(array_shift($products));
                    // This will eventually finish the while loop, but giving each product a chance to be packed.
                }
                 
            } elseif ($this->settings->pack_with_boxes) {
                $products_topack = count($products);
                // Loop [get remaining products] and start with smallest box and go up to biggest box
                // Boxes have been sorted already. (default is from smallest to biggest of boxes that fit by volume)
            	    
                foreach ($this->boxes as $boxindex => $box){
                     
                    if (!empty($box) && is_array($box)){
                        if ($packrotate->has_next){
                            // Packed on previous loop.
                            $this->pack = $packrotate->pnext;
                        } else {
                            // Pack and Check Container
                            $this->pack->pack($products, $box, $this->settings->max_cp_box);
                        }
                        $packrotate->has_next = false;
                         
                        // Only rotate if dimensions are different.
                        $optimalpack = $this->pack;
                        if (!$this->pack->is_box_square($box)){

                            // Try again, but rotate box so that width is now height. This is to optimize the box-picking process.
                            $rotated_box = $this->pack->rotate_box_once($box);
                            $packrotate->p1->pack($products, $rotated_box, $this->settings->max_cp_box);

                            // Rotate box so that length is now height. This is to optimize the box-picking process.
                            $rotated_box2 = $this->pack->rotate_box_once($rotated_box);
                            $packrotate->p2->pack($products, $rotated_box2, $this->settings->max_cp_box);

                            // Analyze packing results. Compares number of items packed and volume packed.
                            $optimalpack = $packrotate->compare_rotated($this->pack);
                        }
                        // Check the next box (if available) for a greater number of products packed.
                        // Only do this check if this current box has packed items to compare against.
                        if (!empty($optimalpack->get_packed_boxes())) {
                            if ($boxindex < $box_count - 1 && !empty($this->boxes[$boxindex + 1])){
                                $next_box = $this->boxes[$boxindex + 1];
                                // Pack next box
                                $packnextbox = $packrotate->next();
                                $packnextbox->pack($products, $next_box, $this->settings->max_cp_box);
                                // Compare by volume
                                $packrotate->has_next = ($packnextbox->get_packed_volume() >  $optimalpack->get_packed_volume());
                                if ($packrotate->has_next) {
                                    // Pack using next box instead. Its next in the foreach.
                                    continue;
                                }
                            }
                        }
                         
                        // Determine if boxes were packed.
                        if (!empty($optimalpack->get_packed_boxes())){
                            
                            // Great! Products were packed in this box.
                            $container = $optimalpack->get_container_complete($optimalpack->get_container_box());
                            
                            // Add to containers array.
                            $containers[] = $container;
        
                            // Loop to pack remaining products
                            $products = $optimalpack->get_remaining_boxes();
                             
                            // Optimize the order of the boxes for the next package.
                            $this->optimize_sort_boxes($products);
        
                            // Break from foreach (the parent 'while loop' will start it up again for the next set of boxes).
                            // This should work fine because the $products array was just updated with get_remaining_boxes()
                            break;
                             
                        } // end if
                        // If none remaining, break from foreach.
                        if (!is_array($products) || count($products) == 0){
                            break;
                        }
                    } // endif
                    // else loop and try next, slightly larger box.
        
                } // end foreach
                 
                if ($products_topack == count($products) && $products_topack > 0){
                    // No products were packed this loop. (probably too big or too heavy, hitting the max)  Have to finish this while loop.
                    // Switch to non-box method?
                    if ($this->settings->boxes_switch){
                        $box_switch = true;
                        continue;
                    } else {
                        // Alternatively, break from loop and... ship products individually. Add remaining products as containers themselves
                        // Remove this (array_shift) as it was just packed individually.
                        $containers[] = $this->to_container(array_shift($products));
                        // This will eventually finish the while loop, but giving each product a chance to be packed.
                    }
                } // endif
            } // endif
             
        } // end while
        
        return $containers;
    }
    
    
    /**
     * Uses Volume Packing for products
     * @param array $products
     * @param array $boxes
     * @return array $containers
     */
    public function volumepack($products, $boxes, $containers = array()){
        // Set up Boxes
        $this->boxes = $boxes;
        $box_switch = false;
        
        // Loop over products to pack.
        while (!empty($products) && is_array($products)){
        
            $products_topack = count($products);
             
            // Optimistic pack - no box containers defined.
            if (!$this->settings->pack_with_boxes || $box_switch){
                
                // Pack by Volume
                $this->pack->pack_by_volume($products, null, $this->settings->max_cp_box);
                $packed_boxes = $this->pack->get_packed_boxes();
                
                // Determine if boxes were packed.
                if (is_array($packed_boxes) && count($packed_boxes) > 0){
                    // Get Container (Includes Products packed)
                    $container = $this->pack->get_container_complete();
                    // Add to containers array.
                    $containers[] = $container;
                
                    // Loop to pack remaining products
                    $products = $this->pack->get_remaining_boxes();
                }// endif
                
                if ($products_topack == count($products) && $products_topack > 0){
                    // No products were packed this loop. (probably too big or too heavy, hitting the max)  Have to finish this while loop.
                    // Best thing to do is pack the largest product separately. Products were sorted as largest to smallest, so remove first product.
                    // Remove this as it was just packed individually.
                     $containers[] = $this->to_container(array_shift($products));
                    // This will eventually finish the while loop, but giving each product a chance to be packed.
                }
                 
        
            } elseif ($this->settings->pack_with_boxes){
                // Pack with Predefined Boxes
                 
                foreach ($this->boxes as $box){
                    if (!empty($box) && is_array($box)){
                         
                        // Pack by Volume
                        $this->pack->pack_by_volume($products, $box, $this->settings->max_cp_box);
                        $packed_boxes = $this->pack->get_packed_boxes();
                        
                        // Determine if boxes were packed.
                        if (is_array($packed_boxes) && count($packed_boxes) > 0){
                            // Get Container (Includes Products packed)
                            $container = $this->pack->get_container_complete($box);
                            // Add to containers array.
                            $containers[] = $container;
                        
                            // Loop to pack remaining products
                            $products = $this->pack->get_remaining_boxes();
                            
                            // Optimize the order of the boxes for the next package.
                            $this->optimize_sort_boxes($products);
                            
                            // Break from foreach (the parent 'while loop' will start it up again for the next set of boxes).
                            // This should work fine because the $products array was just updated with get_remaining_boxes()
                            break;
                        }// endif
                        
                        // If none remaining, break from foreach.
                        if (!is_array($products) || count($products) == 0){
                            break;
                        }
                         
                    }// endif
                }// end foreach
                 
                if ($products_topack == count($products) && $products_topack > 0){
                    // No products were packed this loop. (probably too big or too heavy, hitting the max)  Have to finish this while loop.
                    // Switch to non-box method?
                    if ($this->settings->boxes_switch){
                        $box_switch = true;
                        continue;
                    } else {
                        // Alternatively, break from loop and... ship products individually. Add remaining products as containers themselves
                        // Best thing to do is pack the largest product separately. Products were sorted as largest to smallest, so remove first product.
                        // Remove (array_shift) this as it was just packed individually.
                        $containers[] = $this->to_container(array_shift($products));
                        // This will eventually finish the while loop, but giving each product a chance to be packed.
                    }
                } // endif
                 
            }
             
        }
        
        return $containers;
         
    }
    
    
    // Converts a product array to a container array.
    function to_container($product){
        // put product into its own ['products']
        $container = $product;
        // products are inside a level array.
        $container['products'] = array(array($product));
        return $container;
    }
    
    function get_max_box(){
        // usort(boxes)  Sorts from smallest to biggest)
        usort($this->boxes, array(&$this, 'sort_boxes'));
        // Use largest defined box. (It is last box in $boxes array because of usort that was just called)
        return $this->boxes[count($this->boxes)-1];
    }
    function init_max_box() {
        // If use largest box as max box (instead of defined by service provider)
        if ($this->settings->pack_with_boxes && $this->settings->largest_as_max_box && count($this->boxes) > 0){
            $max_box = $this->get_max_box();
            if (!empty($max_box) && is_array($max_box)){
                $this->settings->max_cp_box['length'] = $max_box['length'];
                $this->settings->max_cp_box['width'] = $max_box['width'];
                $this->settings->max_cp_box['height'] = $max_box['height'];
            }
        }
    }

    
    /*
     * Optimize the order of boxes based on the largest product
     */
    function optimize_sort_boxes($products,$max_box = null){
        // usort(boxes)  Sorts from smallest to biggest)
        usort($this->boxes, array(&$this, 'sort_boxes'));
        // Use largest defined box. (It is last box in $boxes array because of usort that was just called)
        if (empty($max_box)){
            $max_box = $this->boxes[count($this->boxes)-1];
        }
        // Intelligently choose boxes to pack with.
        if (count($this->boxes) > 1 && count($products) > 0){
            $max_cubic = $max_box['length'] * $max_box['width'] * $max_box['height'];
            
            $cubic = floatval($this->pack->total_cubic($products));
            $product_longest = $this->pack->longest_dimensions($products);
            
            // 1. If cart $cubic is more than $max_cubic, then use the biggest box first.
            if ($cubic >= $max_cubic){
                // Use biggest box first. (it's last, so move it to first)
                $mbox = array_pop($this->boxes);    // remove from end of array.
                array_unshift($this->boxes, $mbox); // prepend to array
            } else {
                // Choose box that is *just* more than total cubic.
                for($i=0;$i<count($this->boxes);$i++){
                    $box_cube = floatval($this->boxes[$i]['cubic']);
                    if ($box_cube >= $cubic){
                        // This box should fit.
                        // Check longest dimensions
                        if ( $this->pack->box_fit($product_longest, $this->boxes[$i])) {
                            if ($i > 0){
                                // Move to first.
                                $mbox = $this->boxes[$i];
                                unset($this->boxes[$i]); // remove from array.
                                array_unshift($this->boxes, $mbox); // prepend to array
                                break; // stop the loop.
                            }
                            // Best box is first already.
                            break;
                        }
                    }
                } // end for
            } // endif
        }
    }
    
    
    
    
    // Sort Boxes function. Ascending
    function sort_boxes($a, $b){
        $a_max = max($a['length'], $a['width'], $a['height']);
        $b_max = max($b['length'], $b['width'], $b['height']);
        if ($a['cubic'] == $b['cubic'] && $a_max == $b_max) {
            return 0;
        }
        if ($a['cubic'] == $b['cubic'] && $a_max < $b_max) {
            return -1;
        }
        return ($a['cubic'] < $b['cubic']) ? -1 : 1;
    }
    
    /**
     * Utility function that returns the total volume of a box / container
     *
     * @access public
     * @param array $box
     * @returns float
     */
    function _get_volume($box)  {
        if(!is_array($box) || count(array_keys($box)) < 3) {
            throw new InvalidArgumentException("_get_volume function only accepts arrays with 3 values (length, width, height)");
        }
    
        return $box['length'] * $box['width'] * $box['height'];
    }
    
    // Returns log with pack log appended
    function get_log($include_pack = false) {
        return $include_pack ? array_merge($this->log,array($this->pack->get_debug_log())) : $this->log;
    }
    
}


class cpwebservice_packrotate {

    /**
     * __construct function.
     *
     * @access public
     * @return cpwebservice_packrotate
     */
    function __construct() {
        $this->init();
    }

    /*
     * Usage:
     * $packrotate = new cpwebservice_packrotate();
     * $pack1 = $packrotate->p1;
     */

    public $p1    = null;
    public $p2    = null;
    public $pnext = null;
    public $has_next = false;
    //public $usenext = false;
     /*
     * Init/Construct
     */
    private function init() {
        $this->p1 = new cpwebservice_pack();
        $this->p2 = new cpwebservice_pack();
    }

    /*
    * Next pack
    */
    public function next() {
        $this->pnext = new cpwebservice_pack();
        return $this->pnext;
    }

    /*
    * Compare 2x rotated box packing to primary pack
    */
    public function compare_rotated($p){
        $optimalpack = $this->optimal($p, $this->p1);
        $optimalpack = $this->optimal($optimalpack, $this->p2);

        return $optimalpack;
    }
    /*
    * Return optimal box pack
    */
    public function optimal($p, $r){
        // Use the one that has more cubic volume, as that means more items were packed.
        if(!empty($p->get_packed_boxes()) && !empty($r->get_packed_boxes())
            && $r->get_packed_volume() >  $p->get_packed_volume()){
            // Continue with the rotated box instead, since it was more efficient.
            return $r;
        }
        // Continue with the packed box
        return $p;
    }

    

}