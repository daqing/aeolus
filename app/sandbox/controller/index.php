<?php
  /**
   * Index controller in 'sandbox' module
   *
   */
  
  function index()
  {
    $model = app_model_factory('sandbox','Index_Index_Model');
	$view = app_view_factory('sandbox','Index_Index_View');
    
	$view->title = 'Sandbox';
	$view->render();
	
  }

?>
