<?php if( basename(__FILE__) == basename($_SERVER['REQUEST_URI'])){
        die('<h3>[ERROR 400] BAD REQUEST.</h3>');
	  }
  /**
   * MySQL configuration
   *
   * @author Kinch Zhang <kinch.zhang@gmail.com>
   *
   */

  define('MYSQL_HOST','localhost');
  define('MYSQL_PORT',3306);
  define('MYSQL_USER','php');
  define('MYSQL_PASSWORD','php');
  define('MYSQL_SCHEMA','php');

?>
