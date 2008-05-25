<?php if(! defined('APP_STARTED')){die('<h3>BAD REQUEST.</h3>');}
  /**
   * htmlpurifier controller in 'benchmark' module
   *
   */

  function htmlpurifier($argv)
  {
    if( is_array($argv) && 1 == count($argv)){
	  ob_start();
      $start = microtime();

      $sum = intval($argv[0]);

	  AeolusFactory::loadOnce( 'AeolusGuard.php' );
	  $str = '<h1>Hello<span></h1><script>test();</scr></script>';

      for( $i=0; $i < $sum; $i++){
        $pu = AeolusGuard::purify($str);
	  }
	  
	  $end = microtime();
	  ob_end_clean();
      
	  echo '<br/>Start at '.$start;
	  echo '<br/>End at '.$end;
      echo '<br/><br/>Total is '. ($end-$start);
	}else{
	  die('Invalid parameter');
	}
  }
?>
