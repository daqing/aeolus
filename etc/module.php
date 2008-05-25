<?php if( basename(__FILE__) == basename($_SERVER['REQUEST_URI'])){
        die('<h3>BAD REQUEST</h3>');}
  /**
   * Valid modules
   *
   */

  $module = array('index','forum','blog','demo','benchmark');

?>
