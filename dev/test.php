<?php
  /**
   * Unit test script
   *
   * @author Kinch Zhang <kinch.zhang@gmail.com>
   *
   */

  define('AEOLUS_HOME',dirname(dirname(__FILE__)));
  define('SIMPLE_HOME',dirname(dirname(__FILE__)).'/opt/simpletest');
  require_once( SIMPLE_HOME.'/autorun.php' );

  class TestAll extends UnitTestCase
  {
    function testAll()
	{
	  # Run all testcases
      $test = scandir( AEOLUS_HOME.'/dev/test/' );
      foreach( $test as $v ){
	    if( $v != '.' and $v != '..' ){
		  $path = AEOLUS_HOME."/dev/test/$v";
	      require_once( $path );
          $class_name = basename($path,'.php');
		  echo "class name: $class_name \n";
		  if( class_exists($class_name) ){
		    $obj = & new $class_name();
			if( class_exists( 'TextReporter' ) ){
			  $reporter = & new TextReporter();
			  var_dump($reporter);
			}else{
			  die( "class TextReporter not found");
			}
			$obj->run( $reporter );
		  }
		}
		    
      }
	 }
   }

?>
