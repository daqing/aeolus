<?php
    /**
	 * Index controller in 'index' module
	 *
	 */
    function index()
	{
	  #echo 'Hello,Aeolus Framework !';
	  $view = a_view_factory('index','IndexIndexView');

	  $view->title = 'Index';
	  $view->show();
	}	

?>
