<?php if( basename($_SERVER['REQUEST_URI']) == basename(__FILE__)){
        die('Your request is invalid.<br/>System exited.');
	  }

    /**
     * Valid modules
     * 
     * @author Qingcheng Zhang <kinch.zhang@gmail.com>
     * @copyright Copyright (c) 2008-2009, CityGeneration, Inc. (http://www.citygeneration.com)
     * 
     */

    # This array holds all the valid modules.
	$module = array('index','admin','sandbox','ajax');
?>
