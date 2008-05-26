<?php if(! defined('APP_STARTED')){die('<h3>BAD REQUEST.</h3>');}
  /**
   * index controller in 'benchmark' module
   *
   */

  function index()
  {
    $view = AeolusFactory::makeView('IndexIndexView');

	$view->title = 'Benchmark';
	$view->show();
  }
?>
