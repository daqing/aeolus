<?php
  /**
   * Loaders
   *
   * Load PHP file only once
   *
   * @param $path the *absolute* path of the file
   * @return void
   *
   */
   # Load a file, only once
   function a_load($path)
   {
     if( !isset($GLOBALS['included'][$path]) ){
       clearstatcache();
	   if( file_exists($path) && substr($path,-4,4) == '.php' ){
	   	 # This is a PHP file so it's safe to include it
	     require($path);
	     $GLOBALS['included'][$path] = true;
	   }else{	   	 
	     if( APP_DEBUG ){
	       echo( '<h3>[ERROR 404] FILE: '.$path.' NOT FOUND.</h3>' );	       
		 }else{
	       die('<h3>[ERROR 400] BAD REQUEST.</h3>');
		 }
	   }
	 }
   }
   
   # Load a helper function
   function a_helper_load($module,$helper)
   {
     a_load( AEOLUS_ROOT."/app/$module/helper/$helper.php" );
   }
?>
