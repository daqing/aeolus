<?php if(! defined('APP_STARTED')){die('<h3>BAD REQUEST.</h3>');}
  /**
   * index controller in 'demo' module
   *
   */

  function index()
  {
	$model = AeolusFactory::makeModel('demo','TestModel');
	var_dump($model);
  }
?>
