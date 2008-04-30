<?php
  /**
   * index controller in 'demo' module
   *
   */

  function index()
  {
    $model = AeolusFactory::makeModel('demo','IndexIndexModel');
    $view = AeolusFactory::makeView('demo','IndexIndexView',$model);

	$view->title = 'demo';
	$view->show();
  }
?>
