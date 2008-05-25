<?php if(! defined('APP_STARTED')){die('<h3>BAD REQUEST.</h3>');}
  /**
   * index controller in 'benchmark' module
   *
   */

  function index()
  {
    echo 'Hello,world! [From index controller in \'benchmark\' module]';
	global $thisModule;
	echo "<br/>This module is: $thisModule";
  }
?>
