<?php
  /**
   * kernel controller in 'testcase' module
   *
   */

  function index()
  {
    $test = a_testcase_factory('KernelTest');
	$view = a_view_factory('testcase','KernelIndexView',$test);

	$view->title = 'kernel test';
	$view->show();
  }
?>
