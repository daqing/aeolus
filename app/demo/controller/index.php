<?php
  /**
   * index controller in 'demo' module
   *
   */

  function index()
  {
    $view = a_view_factory('demo','IndexIndexView');

	$view->title = 'demo';
	$view->show();
  }
?>
