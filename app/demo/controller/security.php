<?php
  /**
   * security controller in 'demo' module
   *
   */

  function index()
  {
    AeolusFactory::loadOnce( 'AeolusSecurity.php' );
	$input = '<p>You suck if you don\'t filter the input!<h3>Yead</p>';
	echo 'The input is <pre>'.$input.'</pre>';
	echo 'After filter the input , we get <pre>'.AeolusSecurity::purify($input);
	echo '</pre>';
  }
?>
