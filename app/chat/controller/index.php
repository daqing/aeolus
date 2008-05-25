<?php if(! defined('APP_STARTED')){die('<h3>BAD REQUEST.</h3>');}
  /**
   * index controller in 'chat' module
   *
   */

  function index()
  {
    $view = AeolusFactory::makeView('IndexIndexView');
	$view->title = '微言首页';

	$view->show();
  }
?>
