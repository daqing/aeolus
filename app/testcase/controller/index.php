<?php
  /**
   * index controller in 'demo' module
   *
   */

  
  function index()
  {
    $test = a_testcase_factory('LoggingTest');
	$view = a_view_factory('testcase','IndexIndexView',$test);
	
	$view->title = 'Index';
	$view->show();
  }

?>
