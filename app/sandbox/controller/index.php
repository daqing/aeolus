<?php if(! defined('AEOLUS_STARTED')){ die('<h3>BAD REQUEST</h3>');}
  /**
   * index controller in 'sandbox' module
   *
   */

  function index()
  {
    $view = AeolusFactory::makeView('IndexView');
	$view->title = 'Sandbox';

	$view->show();
  }
?>
