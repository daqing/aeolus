<?php
  /**
   * Index controller in 'sandbox' module
   *
   */
  
  function index()
  {
    $model = a_model_factory('sandbox','Index_Index_Model');
	$view = a_view_factory('sandbox','IndexIndexView',$model);
    
	$view->title = 'Sandbox';
	$view->show();
	
  }

?>
