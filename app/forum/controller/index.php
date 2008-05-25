<?php if(! defined('APP_STARTED')){die('<h3>BAD REQUEST.</h3>');}
  /**
   * index controller in 'forum' module
   *
   */

  function index()
  {
    $view = AeolusFactory::makeView('IndexIndexView');
	$view->title = '论坛首页';

	$view->show();
  }
?>
