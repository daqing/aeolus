<?php
  /**
   * Launch application controllers
   *
   * Dispatch a request to the proper controller
   *
   * @param $result array
   * @return void
   *
   */

   function a_launch($result)
   {
	 $controller = AEOLUS_ROOT.'/app/'.$result['module'];
	 $controller.= '/controller/'.$result['controller'].'.php';
	 a_load($controller);

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
	   if( APP_DEBUG ){
	     echo '<h3>[ERROR 404] Function \''.$result['action'].'\' NOT';
	     echo ' DEFINED BY THE \''.$result['controller'].'\' CONTROLLER';
	     echo ' IN THE \''.$result['module'].'\' MODULE.</h3>';
	   }else{
         die('<h3>[ERROR 400] BAD REQUEST.</h3>');
	   }
     }
   }
 ?>
