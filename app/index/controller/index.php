<?php
    /**
	 * Index controller in 'index' module
	 *
	 */
    function index()
	{
	  #echo 'Hello,Aeolus Framework !';
	  $view = app_view_factory('index','Index_Index_View');

	  $view->title = 'Index';
	  $view->render();
	}	

?>
