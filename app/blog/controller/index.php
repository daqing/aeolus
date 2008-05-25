<?php if(! defined('APP_STARTED')){die('<h3>BAD REQUEST.</h3>');}
  /**
   * index controller in 'blog' module
   *
   */

  function index()
  {
    $model = AeolusFactory::makeModel('ListAllBlogs');
	$view = AeolusFactory::makeView('IndexIndexView',$model);

	$view->title = '博客首页';
	$view->show();
  }
?>
