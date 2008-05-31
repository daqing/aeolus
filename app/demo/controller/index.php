<?php if(! defined('AEOLUS_STARTED')){ die('<h3>BAD REQUEST</h3>');}
  /**
   * 'index' controller in 'demo' group
   *
   */

  function index()
  {
    $view = A::mkview('IndexView');

	$view->title = 'Demo';
	$view->show();
  }
?>
