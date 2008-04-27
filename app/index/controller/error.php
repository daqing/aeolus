<?php
  /**
   * Error controller in 'index' module
   *
   */
  function index()
  {
    echo( '<h3>[ERROR 400] BAD REQUEST. </h3>' );
	if( !APP_DEBUG ){
	  die();
	}
  }
?>
