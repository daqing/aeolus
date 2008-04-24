<?php
  /**
   * Apc controller in 'sandbox' module
   *
   */

  function index()
  {
    if(! function_exists('apc_add') ){
	  echo 'APC enabled';
	}else{
	  echo 'APC disabled';
	}
  }
?>
