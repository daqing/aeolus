<?php if(! defined('APP_STARTED')){die('<h3>BAD REQUEST.</h3>');}
  /**
   * login controller in 'index' module
   *
   */

  function login()
  {
    $view = AeolusFactory::makeView('LoginView');
	$view->title = '登录';

	$view->show();
  }
?>
