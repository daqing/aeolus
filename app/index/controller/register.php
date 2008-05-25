<?php if(! defined('APP_STARTED')){die('<h3>BAD REQUEST.</h3>');}
  /**
   * register controller in 'index' module
   *
   */

  function register()
  {
    $view = AeolusFactory::makeView('RegisterFormView');
	$view->title = '注册';

	$view->show();
  }
?>
