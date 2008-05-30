<?php if(! defined('AEOLUS_STARTED')){ die('<h3>BAD REQUEST</h3>');}
  /**
   * index controller in 'index' module
   */

  function index()
  {
    $model = A::mkModel('IndexModel');

    $view = A::mkView('IndexView', $model);
	$view->title = '首页';

	$view->show();
  }
?>
