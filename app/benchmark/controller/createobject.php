<?php
  /**
   * createclass controller in 'benchmark' module
   *
   */

  function index()
  {
    echo '<h3>Start to create 1000 objects of AeolusView class</h3>';
	echo '<h4>'.microtime().'</h4>';
	for($i=0;$i<1000;$i++){
	  $obj = AeolusFactory::makeView('benchmark','CreateClassView');
	}
	echo '<h3>Done.</h3>';
	echo '<h4>'.microtime().'</h4>';
  }
?>
