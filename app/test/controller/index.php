<?php
  /**
   * index controller in 'demo' module
   *
   */

  
  function index()
  {
	$view = a_view_factory('testcase','IndexIndexView');
	
	$view->title = 'Index';
	$view->show();
  }

?>
