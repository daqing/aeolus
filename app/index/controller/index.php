<?php
    /**
	 * Index controller in 'index' module
	 *
	 */
    function index()
	{
	  #echo 'Hello,Aeolus Framework !';
	  $view = AeolusFactory::makeView('index','IndexIndexView');

	  $view->title = 'Index';
	  $view->show();
	}	

?>
