<?php if(! defined('APP_STARTED')){die('<h3>BAD REQUEST.</h3>');}
  /**
   * index controller in 'demo' module
   *
   */

  function index()
  {
    global $thisModule;
	echo $thisModule;

	$model = AeolusFactory::makeModel('demo','TestModel');
	echo $model->escape("like't");
  }
?>
