<?php
  /**
   * Aeolus_dispatch function
   *
   * Dispatch a request to the proper controller
   *
   * @param $result array
   * @return void
   *
   */

   function aeolus_dispatch($result)
   {
	 $controller = AEOLUS_ROOT.'/app/'.$result['module'];
	 $controller.= '/controller/'.$result['controller'].'.php';
	 aeolus_load($controller);

	 # Check if the action function has been defined
	 if( function_exists($result['action']) ){
	   # Call that function
       if( 0 < $result['argc'] ){
	     $result['action']($result['argv']);
	   }else{
	     $result['action']();
       }

	 }else{
	   # Fatal error: function doesn't exist
	   echo '<br/>[ERROR] function \''.$result['action'].'\' not defined ';
	   echo 'in \''.$result['controller'].'\' controller of ';
	   echo ' \''.$result['module'].'\' module.';
	   die();
	 }

   }
 ?>
