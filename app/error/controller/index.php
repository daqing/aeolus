<?php
  /**
   * Index controller in 'error' module
   *
   */

  function index()
  {
    echo '<h3>[ERROR 400] BAD REQUEST.</h3>';
	if( !APP_DEBUG ){
	  die();
	}
  }
?>
