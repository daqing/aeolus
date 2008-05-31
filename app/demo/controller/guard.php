<?php if(! defined('AEOLUS_STARTED')){ die('<h3>BAD REQUEST</h3>');}
  /**
   * 'guard' controller in 'demo' group
   *
   */

  function guard()
  {
    $start = microtime();
    A::ld('AGuard');
	$data = AGuard::purify('<span>hello<p>world</span></p></span>');
	$end = microtime();
	echo $data;
	$time = $end - $start;
	$sec = $time > 1 ? ' seconds' : ' second';
	echo "<br/>-------------<br/>Started at: $start";
	echo "<br/>Ended at: $end";
	echo '<br/>-------------<br/>Lasted for: '.$time.$sec;
  }
?>
