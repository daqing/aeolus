<?php
  /**
   * Front controller 
   *
   * @copyright Copyright (c) 2008-2009, CityGeneration, Inc. (http://citygeneration.com)
   * @author Qingcheng Zhang <kinch.zhang@gmail.com>
   * 
   */

    # Load configuration file
    require( 'etc/aeolus.php' );

    # load the controller and utils functions 
    require(AEOLUS_ROOT.'/kernel/core/controller.php');
    require(AEOLUS_ROOT.'/kernel/core/utils.php');
    
	# Init
	aeolus_init();

	# Dispatch the HTTP request
    aeolus_dispatch(aeolus_route($_SERVER['REQUEST_URI']));

	# Show debug info if enabled
	if( AEOLUS_DEBUG && function_exists( 'xdebug_get_tracefile_name' ) ){
	  # Xdebug: get trace file
	  echo '<pre>';
	  echo file_get_contents(xdebug_get_tracefile_name());
	  echo '</pre>';
	}

?>
