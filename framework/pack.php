<?php

/**
 * Largest Area Fit First (LAFF) 3D box packing algorithm class
 *
 * @author Maarten de Boer <info@maartendeboer.net>
 * @copyright Maarten de Boer 2012
 * @version 1.2
 * @contributor Jamez Picard (TrueMedia.ca) jameztrue
 *
 * Also see this PDF document for an explanation about the LAFF algorithm:
 * @link http://www.zahidgurbuz.com/yayinlar/An%20Efficient%20Algorithm%20for%203D%20Rectangular%20Box%20Packing.pdf
 *
 * Copyright (C) 2012 Maarten de Boer
 * Copyright (C) 2013-2022 Jamez Picard
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */
class cpwebservice_pack {
	
	/** @var array $boxes Array of boxes to pack */
	private $boxes = null;
	
	/** @var array $packed_boxes Array of boxes that have been packed */
	private $packed_boxes = null;
	
	/** @var int $level Current level we're packing (0 based) */
	private $level = -1;
	
	/** @var array $container_dimensions Current container dimensions */
	private $container_dimensions = null;

    /** @var array $container_box Provided container */
	private $container_box = null;
	
	/** @var bool $container_preset Container dimensions preset */
	private $container_preset = false;
	
	/** @var array $max_container Maximum container dimensions and 'weight' */
	private $max_container = null;
	
	/** @var string debug log */
	private $log = '';
	
	/** @var bool enable debug log */
	private $debug = false;
	
	/**
	 * Constructor of the BoxPacking class
	 *
	 * @access public
	 * @param array $boxes Array of boxes to pack
	 */
	function __construct($boxes = null, $container = null, $max = null)
	{		
	    // If parameters are provided in constructor: pack.
		if(isset($boxes) && is_array($boxes)) {
		    $this->pack($boxes, $container, $max);
		}
	}
	
	/**
	 * Start packing boxes
	 * 
	 * @access public
	 * @param array $boxes
	 * @param array $container Set fixed container dimensions
	 * @returns void
	 */
	function pack($boxes = null, $container = null, $max = null) {
		if(isset($boxes) && is_array($boxes)) {
			$this->boxes = $boxes;
			$this->packed_boxes = array();
			$this->level = -1;
			$this->container_dimensions = null;
            $this->container_preset = false;
			
			// Calculate container size
			if(!is_array($container) || $container == null) {
				$this->container_dimensions = $this->_calc_container_dimensions();
			}
			else 
			{
				if(!array_key_exists('length', $container) || !array_key_exists('width', $container)) {
						throw new InvalidArgumentException("Pack function only accepts array with (length, width) as argument for \$container");
				}
                $this->container_box = $container;
				$this->container_preset = true;
				$this->container_dimensions['length'] = $container['length'];
				$this->container_dimensions['width'] = $container['width'];
				
				// Note: do NOT set height, it will be calculated on-the-go
				$this->container_dimensions['height'] = 0;
				// Weight will be added for each box.
				$this->container_dimensions['weight'] = 0;
			}
			
			// Maximum Container size (& weight)
			if ($max != null && is_array($max)){
				if(!array_key_exists('height', $max) && !array_key_exists('weight', $max)) {
					throw new InvalidArgumentException("Function _pack only accepts array (height, weight) as argument for $max");
				}
				$this->max_container = $max;

				if ($this->container_preset && $container != null){
    				// If box/container provided, change max_container.height to the box/container's height, since this will limit it.
    				$this->max_container['height'] =  $container['height'];
				}
			}
		}
		
		if(!isset($this->boxes)) {
			throw new InvalidArgumentException("Pack function only accepts array (length, width, height) as argument for \$boxes or no boxes given!");
		}
		
		$this->pack_level();
	}
	
	/**
	 * Get remaining boxes to pack
	 *
	 * @access public
	 * @returns array
	 */
	function get_remaining_boxes() {
		return $this->boxes;
	}
	
	/**
	 * Get packed boxes
	 *
	 * @access public
	 * @returns array
	 */
	function get_packed_boxes() {	
		return $this->packed_boxes;
	}
	
	/**
	 * Get container dimensions
	 *
	 * @access public
	 * @returns array
	 */
	function get_container_dimensions() {
		return $this->container_dimensions;
	}
	
	/**
	 * Get container volume
	 *
	 * @access public
	 * @returns float
	 */
	function get_container_volume() {
		if(!isset($this->container_dimensions)) {
			return 0;
		}
	
		return $this->_get_volume($this->container_dimensions);
	}
	
	/**
	 * Get container weight
	 *
	 * @access public
	 * @returns float
	 */
	function get_container_weight() {
	    if(!isset($this->packed_boxes) || count($this->packed_boxes) == 0) {
	        return 0;
	    }
	    
	    // Get weight
	    $weight = 0;
	    for($i = 0; $i<count($this->packed_boxes); $i++){
	        foreach($this->packed_boxes[$i] as $p){
	            $weight += (isset($p['weight']) ? $p['weight'] : 0);
	        }
	    }
	    
	    return $weight;
	}
	
	/**
	 * Provides full Container Information (including Products and Box information)
	 *
	 * @access public
	 * @param $box Box used in packing (as a container). If none was used, pass null or omit.
	 * @returns array
	 */
	function get_container_complete($box = null){
	    // Expected to be called *after* packing.
	    $container = $this->get_container_dimensions(); // returns @array('length' => 0,'width' => 0,'height' => 0);
	    $container['cubic'] = $this->get_container_volume(); // number
	    $container['weight'] = $this->get_container_weight();
	    
	    
	    if (!empty($box) && is_array($box)){
	        
    	    if (floatval($container['height']) > floatval($box['height'])) {
    	        // Because 'height' is dynamically calculated so we have made sure it actually fits inside this box)
    	        // If the dynamic height is greater than the box, we need to skip this container.
    	        return null;
    	    }
    	    
    	    // Add box information
    	    $container['box_weight'] = $box['weight'];
    	    $container['box_margin'] = $box['margin'];
    	    $container['box_name'] 	 = $box['name'];
    	    // If the box has weight, add it to the shipping weight.
    	    if (floatval($container['box_weight']) > 0){
    	        $container['weight'] += floatval($container['box_weight']);
    	    }

    	    // Container height is dynamic in the box-packing algorithm.  Set to the defined box height, because that's the true package size.
    	    $container['height'] = $box['height'];
    	    // Recalc cubic because of height change.
    	    $container['cubic'] = $container['length'] * $container['width'] * $container['height'];
	    }
	    
	    // Packed products
	    $container['products'] = $this->get_packed_boxes();
	    
	    
	    return $container;
	}
	
	/**
	 * Get number of levels
	 *
	 * @access public
	 * @returns int
	 */
	function get_levels() {
		return $this->level + 1;
	}
	
	/**
	 * Get total volume of packed boxes
	 *
	 * @access public
	 * @returns float
	 */
	function get_packed_volume() {
		if(!isset($this->packed_boxes)) {
			return 0;
		}
		
		$volume = 0;
		
		for($i = 0; $i < count(array_keys($this->packed_boxes)); $i++) {
			foreach($this->packed_boxes[$i] as $box) {
				$volume += $this->_get_volume($box);
			}
		}
		
		return $volume;
	}
	
	/**
	 * Get number of levels
	 *
	 * @access public
	 * @returns int
	 */
	function get_remaining_volume() {
		if(!isset($this->packed_boxes)) {
			return 0;
		}
	
		$volume = 0;
		
		foreach($this->boxes as $box) {
			$volume += $this->_get_volume($box);
		}
		
		return $volume;
	}
	
	/**
	 * Get dimensions of specified level
	 *
	 * @access public
	 * @param int $level
	 * @returns array
	 */
	function get_level_dimensions($level = 0) {
		if($level < 0 || $level > $this->level || !array_key_exists($level, $this->packed_boxes)) {
			throw new OutOfRangeException("Level {$level} not found!");
		}
	
		$boxes = $this->packed_boxes;
		$edges = array('length', 'width', 'height');
		
		// Get longest edge
		$le = $this->_calc_longest_edge($boxes[$level], $edges);
		$edges = array_diff($edges, array($le['edge_name']));
		
		// Re-iterate and get longest edge now (second longest)
		$sle = $this->_calc_longest_edge($boxes[$level], $edges);
		
		return array(
			'width' => $le['edge_size'],
			'length' => $sle['edge_size'],
			'height' => $boxes[$level][0]['height'],
			'weight' => 0
		);
	}
	
	/**
	 * Get longest edge from boxes
	 *
	 * @access private
	 * @param array $edges Edges to select the longest from
	 * @returns array
	 */
	private function _calc_longest_edge($boxes, $edges = array('length', 'width', 'height')) {
		if(!isset($boxes) || !is_array($boxes)) {
			throw new InvalidArgumentException('_calc_longest_edge function requires an array of boxes, '.gettype($boxes).' given');
		}
		
		// Longest edge
		$le = 0;		// Longest edge
		$lef = null;	// Edge field (length | width | height) that is longest
		
		// Get longest edges
		foreach($boxes as $k => $box) {
			foreach($edges as $edge) {
				if(array_key_exists($edge, $box) && $box[$edge] > $le) {
					$le = $box[$edge];	
					$lef = $edge;
				}
			}
		}
		
		return array(
			'edge_size' => $le,
			'edge_name' => $lef
		);
	}
	
	/**
	 * Calculate container dimensions
	 *
	 * @access public
	 * @returns array
	 */
	function _calc_container_dimensions() {
		if(!isset($this->boxes)){
			return array(
				'length' => 0,
				'width' => 0,
				'height' => 0,
				'weight' => 0
			);
		}
		
		$boxes = $this->boxes;
		
		$edges = array('length', 'width', 'height');
		
		// Get longest edge
		$le = $this->_calc_longest_edge($boxes, $edges);
		$edges = array_diff($edges, array($le['edge_name']));
		
		// Re-iterate and get longest edge now (second longest)
		$sle = $this->_calc_longest_edge($boxes, $edges);
		
		// Calculate 2x shortest lxw edge to allow side-by-side (cube) packing for simlar boxes. Alternatively, it will end up as long boxes, which are not typical.
		if (count($boxes) >= 4){
		    // Get shortest edges x2
		    $shorth = $this->_calc_shortest_edge_xn($boxes, array('height'));
		    $shorte = $this->_calc_shortest_edge_xn($boxes);
		    // Height has to be greater than 1/2 L or W edge to ensure it is not a flat item.
		    if ($sle['edge_size'] > 0 && $shorth['edge_size'] > ($sle['edge_size']/2) 
		        &&  $shorte['edge_size'] > $sle['edge_size']){
		        $sle['edge_size'] = $shorte['edge_size'];
		        if($this->debug) { $this->log .= "Shorte: " . json_encode($shorte) . "\n"; }
		    }
		    
		}
		
		return array(
			'length' => $sle['edge_size'],
			'width' => $le['edge_size'],
			'height' => 0,
			'weight' => 0
		);
	}
	
	/*
	 * Shortest Edge (2x boxes).
	 * Returns shortest + 2nd shortest edge. (for avg cubing).
	 */
	private function _calc_shortest_edge_xn($boxes, $edges = array('length', 'width')){
	    // Shortest edge
	    $se = 999999;	// Shortest edge
	    $se2 = 0; // 2nd Shortest edge.
	    $sef = null;	// Edge field (length | width) that is shortest
	    
	    // Get shortest edges
	    foreach($boxes as $k => $box) {
	        foreach($edges as $edge) {
	            if(array_key_exists($edge, $box) && $box[$edge] <= $se) {
	                //2nd shortest.
	                $se2 = $se;
	                $se = $box[$edge];
	                $sef = $edge;
	            }
	        }
	    }
	    
	    // Valid values.
	    $se  =  ($se != 999999 ? $se : 0);
	    $se2 =  ($se2 != 999999 ? $se2 : 0);
	    
	    // Return se+se2.
	    return array(
	        'edge_size' => $se + $se2,
	        'edge_name' => $sef
	    );
	}
	
	/**
	 * Utility function that returns the total cubic volue of all boxes in the list.
	 *
	 * @access public
	 * @param array
	 * @returns decimal
	 * **/
	public function total_cubic($boxes){
	    $cubic = 0;
	    // Calc total cubic
	    foreach($boxes as $k=>$box){
	        $cubic += $this->_get_volume($box);
	    }
	    return $cubic;
	}
	
	/**
	 * Get biggest dimensions
	 *
	 * @access public
	 * @param array $boxes
	 * @returns array
	 */
	public function longest_dimensions($boxes) {
	    
	    $edges = array('length', 'width', 'height');
	
	    // Get longest edge
	    $le = $this->_calc_longest_edge($boxes, $edges);
	    $edges = array_diff($edges, array($le['edge_name']));
	
	    // Re-iterate and get longest edge now (second longest)
	    $sle = $this->_calc_longest_edge($boxes, $edges);
	    $edges = array_diff($edges, array($sle['edge_name']));
	    
	    // Re-iterate and get final longest edge
	    $fle = $this->_calc_longest_edge($boxes, $edges);
	    $edges = array_diff($edges, array($fle['edge_name']));
	
	    return array(
	        'width' => $le['edge_size'],
	        'length' => $sle['edge_size'],
	        'height' => $fle['edge_size'],
	        'weight' => 0
	    );
	}
	
	/**
	 * Utility function to swap two elements in an array
	 * 
	 * @access public
	 * @param array $array
	 * @param mixed $el1 Index of item to be swapped
	 * @param mixed $el2 Index of item to swap with
	 * @returns array
	 */ 
	function _swap($array, $el1, $el2) {
		if(!array_key_exists($el1, $array) || !array_key_exists($el2, $array)) {
			throw new InvalidArgumentException("Both element to be swapped need to exist in the supplied array");
		}
	
		$tmp = $array[$el1];
		$array[$el1] = $array[$el2];
		$array[$el2] = $tmp;
		
		return $array;
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
	
	/**
	 * Check if box fits in specified space
	 *
	 * @access private
	 * @param array $box Box to fit in space
	 * @param array $space Space to fit box in
	 * @returns bool
	 */
	private function _try_fit_box($box, $space)  {
		if(count($box) < 3) {
			throw new InvalidArgumentException("_try_fit_box function parameter $box only accepts arrays with 3 values (length, width, height)");
		}
		
		if(count($space) < 3) {
			throw new InvalidArgumentException("_try_fit_box function parameter $space only accepts arrays with 3 values (length, width, height)");
		}
	
		$sides = array('length','width', 'height');
		foreach($sides as $side) {
		    if(array_key_exists($side, $space)) {
		        if($box[$side] > $space[$side]) {
		            return false;
		        }
		    }
		}
		
		return true;
	}
	
	/**
	 * Check if box fits in specified space
	 * and rotate (3d) if necessary
	 *
	 * @access public
	 * @param array $box Box to fit in space
	 * @param array $space Space to fit box in
	 * @returns bool
	 */
	function _box_fits($box, $space) {
		//$box = array_values($box);
		//$space = array_values($space);
		
		if($this->_try_fit_box($box, $space)) {
			return true;
		}
		
		$sides = array('length','width', 'height');
		foreach($sides as $side){
		    // This temp box is only to get the 2 sides to swap.
		    // Temp box size (arrays are assigned by copy)
		    $t_box = $box;
		    	
		    // Remove fixed column from list to be swapped
		    unset($t_box[$side]);
		    // Keys to be swapped
		    $t_keys = array_keys($t_box);
		    	
		    // Temp box with swapped sides
		    $s_box = $this->_swap($box, $t_keys[0], $t_keys[1]);
		    	
		    if($this->_try_fit_box($s_box, $space)){
		        return true;
		    }
		}
		
		return false;
	}

	/**
	 * Start a new packing level
	 *
	 * @access private
	 * @returns void
	 */
	private function pack_level() {
		$biggest_box_index = null;
		$biggest_surface = 0;
		
		$this->level++;
		
		// Find biggest (widest surface) box with minimum height
		foreach($this->boxes as $k => $box)
		{
			$surface = $box['length'] * $box['width'];
			
			if($surface > $biggest_surface) {
				$biggest_surface = $surface;
				$biggest_box_index = $k;
			}
			elseif($surface == $biggest_surface) {
				if(!isset($biggest_box_index) || (isset($biggest_box_index) && $box['height'] < $this->boxes[$biggest_box_index]['height']))
					$biggest_box_index = $k;
			}
		}
		
		// Get biggest box as object
		$biggest_box = $this->boxes[$biggest_box_index];
		
		
		// Check against max-weight, max-height and max-girth for container.  If it will become too big, return.
		if (!$this->_check_max($biggest_box)){
		    return;
		}		

		if ($this->container_preset){
			// Check against max-length for container.
			// Check against max-width for container.
			if (($this->container_dimensions['length'] < $biggest_box['length'] || $this->container_dimensions['width'] < $biggest_box['width'])
					&&  ($this->container_dimensions['width'] < $biggest_box['length'] || $this->container_dimensions['length'] <  $biggest_box['width']) ){
				// The box will not fit the surface area of the container (given it's length & width).  It will need to be 3d rotated in order to fit.
			    if ($this->debug){ $this->log .= 'Not fit surface area: '.$biggest_box_index . "\n"; }
					return;
			}
		}
		
		// Add to packed boxes.
		$this->packed_boxes[$this->level][] = $biggest_box;
		
		// Add to container weight
		$this->_pack_container_weight($biggest_box);
		
		// Set container height (ck = ck + ci)
		$this->container_dimensions['height'] += $biggest_box['height'];
		
		// Remove box from array (ki = ki - 1)
		unset($this->boxes[$biggest_box_index]);
		
		// Check if all boxes have been packed
		if(count($this->boxes) == 0)
			return;
		
		$c_area = $this->container_dimensions['length'] * $this->container_dimensions['width'];
		$p_area = $biggest_box['length'] * $biggest_box['width'];
		
		// No space left (not even when rotated / length and width swapped)
		if($c_area - $p_area <= 0) {
		    if ($this->debug){ $this->log .= 'Next level: '. ($this->level + 1) . "\n"; }
			$this->pack_level();
		}
		else { // Space left, check if a package fits in
			$spaces = array();
			
			if($this->container_dimensions['length'] - $biggest_box['length'] > 0) {
				$spaces[] = array(
					'length' => $this->container_dimensions['length'] - $biggest_box['length'],
					'width' => $this->container_dimensions['width'],
					'height' => $biggest_box['height'],
					'weight' => $biggest_box['weight']
				);
			}
			
			if($this->container_dimensions['width'] - $biggest_box['width'] > 0) {
				$spaces[] = array(
					'length' => $biggest_box['length'],
					'width' => $this->container_dimensions['width'] - $biggest_box['width'],
					'height' => $biggest_box['height'],
					'weight' => $biggest_box['weight']
				);
			}
			
			// Fill each space with boxes
			foreach($spaces as $space) {
				$this->_fill_space($space);
			}
			if ($this->debug){ $this->log .= 'Used spaces: '.count($spaces) . "\n"; }
			
			// Start packing remaining boxes on a new level
			if(count($this->boxes) > 0)
				$this->pack_level();
		}
	}
	
	/**
	 * Fills space with boxes recursively
	 *
	 * @access private
	 * @returns void
	 */
	private function _fill_space($space) {	

		// Total space volume
		$s_volume = $this->_get_volume($space);
		
		$fitting_box_index = null;
		$fitting_box_volume = null;
		
		foreach($this->boxes as $k => $box)
		{
			// Skip boxes that have a higher volume than target space
			if($this->_get_volume($box) > $s_volume) {
				continue;
			}
			
			if($this->_box_fits($box, $space)) {
				$b_volume = $this->_get_volume($box);
			
				if(!isset($fitting_box_volume) || $b_volume > $fitting_box_volume) {
					$fitting_box_index = $k;
					$fitting_box_volume = $b_volume;
				}
			}
		}
		
		if(isset($fitting_box_index))
		{
			$box = $this->boxes[$fitting_box_index];
		
			// Check Max for Container
			if ($this->_check_max($this->boxes[$fitting_box_index])) {
			    
    			// Pack box
    			$this->packed_boxes[$this->level][] = $this->boxes[$fitting_box_index];
    			$this->_pack_container_weight($this->boxes[$fitting_box_index]);
    			unset($this->boxes[$fitting_box_index]);
    			
    			// Calculate remaining space left (in current space)
    			$new_spaces = array();
    		
    			if($space['length'] - $box['length'] > 0) {
    				$new_spaces[] = array(
    					'length' => $space['length'] - $box['length'],
    					'width' => $space['width'],
    					'height' => $box['height'],
    					'weight' => $box['weight']
    				);					
    			}
    			
    			if($space['width'] - $box['width'] > 0) {
    				$new_spaces[] = array(
    					'length' => $box['length'],
    					'width' => $space['width'] - $box['width'],
    					'height' => $box['height'],
    					'weight' => $box['weight']
    				);
    			}
    			
    			if(count($new_spaces) > 0) {
    				foreach($new_spaces as $new_space) {
    					$this->_fill_space($new_space);
    				}
    			}
			}
		}
	}

	/*
	 * Returns true if box will fit the max defined for the container.
	 * It checks the max height, max girth and max weight if it is defined for the container.
	 */
	private function _check_max($box){

	    // Check against max-height for container.  If it will become too big, return.
	    if (isset($this->max_container['height']) && ($this->container_dimensions['height'] + $box['height']) > $this->max_container['height']){
	        if ($this->debug){ $this->log .= 'Max height: '.json_encode($box) . "\n"; }
	        return false;
	    }
	    
	    // Check against max-girth for container.  If it will become too big, return.
	    if (isset($this->max_container['girth'])){
	        // Girth: length + (width x 2) + (height x 2)
	        $max_girth = floatval($this->container_dimensions['length'] + $this->container_dimensions['width']*2 + ($this->container_dimensions['height'] + $box['height'])*2);
	        if ($max_girth > floatval($this->max_container['girth'])){
	            if ($this->debug){ $this->log .= 'Max girth: '.json_encode($box) . "\n"; }
	            return false;
	        }
	    }
	    
	    if (isset($box['weight']) && isset($this->max_container['weight']) && ($this->container_dimensions['weight'] + $box['weight']) > $this->max_container['weight']){
	        if ($this->debug){ $this->log .= 'Max weight: '.json_encode($box) . "\n"; }
	        return false;
	    }
	    
	    // It will be fine.
	    return true;
	}
	
	/*
	 * Adds container weight as a box is packed.
	 */
	private function _pack_container_weight($box){
	    // Add to container weight.
	    $this->container_dimensions['weight'] += isset($box['weight']) ? $box['weight'] : 0;
	}
	
    /*
	 * Returns container as constructed.
	 */
    public function get_container_box() {
        return $this->container_box;
    }
	
    /**
	 * Utility function to enable debugging/diagnostics
	 */
	public function enable_debug() {
	    $this->debug = true;
	}
	/**
	 * Provides debug log.
	 */
	public function get_debug_log() {
	    return $this->log;
	}
	
	
	/* 
	 * Pack by Volume
    *  This uses cubic volume to determine packing.
    *  This is used with _volume_pack() and if used, is not able to be used with pack().
    */
	public function pack_by_volume($boxes = null, $container = null, $max = null){
	    
	    if(isset($boxes) && is_array($boxes)) {
	        $this->boxes = $boxes;
	        $this->packed_boxes = array();
	        $this->container_dimensions = null;
	        $this->max_container = null;
	        
	        // Setup Maximums.
	        // Container size (& weight)
	        if (is_array($container)){
	            if(!array_key_exists('height', $container) && !array_key_exists('weight', $container)) {
	                throw new InvalidArgumentException('Function pack_by_volume accepts array (height, weight) as argument for $container');
	            }
	            $this->container_dimensions = array('length'=>$container['length'], 'width'=>$container['width'],'height'=>$container['height'], 'weight'=> $container['weight']);
	        }
	        // Maximum Container size (& weight)
			if (is_array($max)){
				if(!array_key_exists('height', $max) && !array_key_exists('weight', $max)) {
					throw new InvalidArgumentException('Function pack_by_volume accepts array (height, weight) as argument for $max');
				}
				$this->max_container = $max;
			}
			
	        $this->_volume_pack();
	    } // endif
	    
	    
	  }
	    
    /*
     * Performs packing by volume/cubic values
     */
    private function _volume_pack(){
        // Get Max dimensions/cubic for this package
        $packed_boxes = array();
        $current_max = array('length'=>0, 'width'=>0, 'height'=>0);
        $current_cubic = 0;
        $current_weight = 0;
        $p_index=0;
        $remaining_products = array();
        
        // Get max_cubic
        $container_volume = !empty($this->container_dimensions) && is_array($this->container_dimensions) ? $this->_get_volume($this->container_dimensions) : 0;
        $max_volume       = !empty($this->max_container) && is_array($this->max_container) ? $this->_get_volume($this->max_container) : 99999;
        // Use the smallest value (but not if $container_volume is 0)
        $max_cubic_volume = ($container_volume > 0 && $container_volume < $max_volume) ? $container_volume : $max_volume;
        $max_weight = !empty($this->max_container) && is_array($this->max_container) ? $this->max_container['weight'] : 99999;
        
        // Loop boxes/products
        foreach($this->boxes as $p){
            $product_cubic = $this->_get_volume($p);
            // Check Cubic and Dimension Max
            if (($current_cubic + $product_cubic) <= $max_cubic_volume && ($current_weight + $p['weight']) < $max_weight) {
                 
                // Check max
                if (!empty($this->container_dimensions) && !$this->_box_fits($p, $this->container_dimensions)){
                    continue;
                }
                if (!empty($this->max_container) && !$this->_box_fits($p, $this->max_container)){
                    continue;
                }
                 
                // Great, it fits, Add to current package.
                $this->packed_boxes[] = $p;
                $current_cubic += $product_cubic;
                $current_weight += (!empty($p['weight']) ? floatval($p['weight']) : 0);
                // Max dimensions
                if ($p['length'] > $current_max['length']) {  $current_max['length'] = $p['length']; }
                if ($p['width'] > $current_max['width']) {  $current_max['width'] = $p['width']; }
                if ($p['height'] > $current_max['height']) {  $current_max['height'] = $p['height']; }
            } else {
                // Done, hit the max.
                break;
            }
            $p_index++;
        } // endforeach
        
        if (!empty($this->packed_boxes)){
            
            // Get the remaining boxes.
            if (count($this->packed_boxes) == (count($this->boxes))){
                // none left
                $this->boxes = array();
            }elseif ($p_index > 0) {
                // slice the remaining.
                $this->boxes = array_slice($this->boxes, $p_index);
            }
            // Put boxes into level0 to match pack() function
            $this->packed_boxes = array($this->packed_boxes);
            
            // If container not provided, estimate one with cube root of volume packed.
            if (empty($this->container_dimensions)) {
	            // Method: Cube Root volume. (items are assumed already packaged to ship)
	            $dimension = (float)pow($current_cubic, 1.0/3.0);
	        
	            // Set Container dimensions using max dimensions to ensure an item like 1x1x20 is estimated with enough length.
	            $this->container_dimensions = array('length'=>($dimension < $current_max['length'] ? $current_max['length'] : $dimension), 'width'=>($dimension < $current_max['width'] ? $current_max['width'] : $dimension), 'height'=>($dimension < $current_max['height'] ? $current_max['height'] : $dimension));
            } 
        }
        
        // Done.
    }
    
    // Box fitting function
    function box_fit($box, $max){
        if (is_array($box) && is_array($max)){
            // simple value array.
            $fitbox = array($box['width'], $box['height'], $box['length']);
            $fitmax = array($max['width'], $max['height'], $max['length']);
    
            // sorted, largest to smallest dimension by value.
            rsort($fitbox);
            rsort($fitmax);
            
            for($i=0;$i<count($fitbox);$i++){
                if ($fitbox[$i] > $fitmax[$i]){
                    // oops, too big
                    return false;
                }
            }
            
            // if it got through the loop, it fits.
            return true;
        }
        return false;
    }
    
    
    /**
	 * Rotates so that height is the smallest dimension.
	 * In other words, rotates the length to be the longest dimension.
	 * The order of smallest to heights becomes: length, width, then height.
	 * 
	 *
	 * @access public
	 * @param array $boxes Array of boxes to rotate
	 */
    function rotate_package($box){
        
       // Compare each dimension and swap. 
        if ($box['height'] > $box['length']){
            $box = $this->_swap($box, 'height', 'length');
        }
        if ($box['width'] > $box['length']){
            $box = $this->_swap($box, 'width', 'length');
        }
        if ($box['height'] > $box['width']){
            $box = $this->_swap($box, 'height', 'width');
        }
       
        // Return rotated box/array.
        return $box;
    }
    
    /**
     * Rotates so that for each box, height is the smallest dimension
     * Also converts dimensions to float vals instead of strings.
     *
     *
     * @access public
     * @param array $boxes Array of boxes to rotate
     */
    function rotate_boxes(&$boxes){
        for ($i=0;$i<count($boxes);$i++){
    
            //Also converts dimensions to float vals instead of strings.
            $boxes[$i]['length'] = floatval($boxes[$i]['length']);
            $boxes[$i]['width'] = floatval($boxes[$i]['width']);
            $boxes[$i]['height'] = floatval($boxes[$i]['height']);
            $boxes[$i]['cubic'] = floatval($boxes[$i]['length'] * $boxes[$i]['width'] * $boxes[$i]['height']);
    
            // Do rotation
            $boxes[$i] = $this->rotate_package($boxes[$i]);
        }
    }
    
    /**
     * Rotate box so that length and height and width are rotated once.
     *
     * @access public
     * @param array $box box array to rotate
     */
    function rotate_box_once($box){
        // Returns a copy of the array.
        return array_merge($box, array('length'=>$box['height'], 'width'=>$box['length'], 'height'=>$box['width']));
    }
    
    /**
     * Returns if box is square
     *
     * @access public
     * @param array $box box array to identify
     */
    public function is_box_square($box){
        // If all sides are the same dimension
        return (($box['length'] == $box['width'] && $box['width'] == $box['height']));
    }
	    
}
