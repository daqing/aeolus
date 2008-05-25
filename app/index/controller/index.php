<?php if(! defined('APP_STARTED')){die('<h3>BAD REQUEST.</h3>');}
  /**
   * index controller in 'index' module
   *
   */

  function index()
  {
    $model = AeolusFactory::makeModel('IndexTestModel');
    $view = AeolusFactory::makeView('IndexIndexView',$model);

	$view->title = 'index';
	$view->show();
  }
?>
