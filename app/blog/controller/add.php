<?php if(! defined('APP_STARTED')){die('<h3>BAD REQUEST.</h3>');}
  /**
   * add controller in 'blog' module
   *
   */

  function add()
  {
    $model = AeolusFactory::makeModel('AddBlogModel');
	$view = AeolusFactory::makeView('AddBlogView');

	$view->title = '写日志';

	$view->show();
  }
?>
