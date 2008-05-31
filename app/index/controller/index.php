<?php if(! defined('AEOLUS_STARTED')){ die('<h3>BAD REQUEST</h3>');}
  /**
   * 'index' controller in 'index' group
   *
   */

  function index()
  {
    $data = array('index');
    $view = A::mkview('IndexView', $data);

	$view->title = 'Index';
	$view->show();
  }
?>
