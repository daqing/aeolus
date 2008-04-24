<?php
  /**
   * Front controller 
   *
   * @copyright Copyright (c) 2008-2009, CityGeneration, Inc. (http://citygeneration.com)
   * @author Qingcheng Zhang <kinch.zhang@gmail.com>
   * 
   */

    # Load configuration file
	require( 'conf/app/app.php' );
    require( 'conf/core/core.php' );

    # load core functions
    require(AEOLUS_ROOT.'/kernel/core/aeolus_load.php');
    require(AEOLUS_ROOT.'/kernel/core/aeolus_route.php');
    require(AEOLUS_ROOT.'/kernel/core/aeolus_dispatch.php');
    require(AEOLUS_ROOT.'/kernel/core/factory.php');
    
	# Dispatch the HTTP request
    aeolus_dispatch(aeolus_route($_SERVER['REQUEST_URI']));

	# =========================== #
	# Show debug info if enabled  #
	# =========================== #
	if( AEOLUS_DEBUG && function_exists( 'xdebug_get_tracefile_name' ) ){
	  # Xdebug: get trace file
	  echo '<h3>[DEBUG INFO]</h3>';
	  echo '<pre>';
	  echo file_get_contents(xdebug_get_tracefile_name());
	  echo '</pre>';
	}

?>
