<?php if(! defined('APP_STARTED')){die('<h3>BAD REQUEST.</h3>');}
  /**
   * index controller in 'index' module
   *
   */

  function index()
  {
    $view = AeolusFactory::makeView('index','IndexIndexView');

	$view->title = 'index';
	$view->show();
  }
?>
