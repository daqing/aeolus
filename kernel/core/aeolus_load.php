<?php
  /**
   * Aeolus_load function
   *
   * Load PHP file only once
   *
   * @param $path the *absolute* path of the file
   * @return void
   *
   */

   function aeolus_load($path)
   {
     if( !isset($GLOBALS['included'][$path]) ){
       clearstatcache();
	   if( file_exists($path) ){
	     require($path);
	     $GLOBALS['included'][$path] = true;
	   }else{
	     if( APP_DEBUG ){
	       die( '<h3>[ERROR 404] FILE: '.$path.' NOT FOUND.</h3>' );
		 }else{
	       die('<h3>[ERROR 404] RESOURCE NOT FOUND.</h3>');
		 }
	   }
	 }
   }

   function app_helper_load($module,$helper)
   {
     aeolus_load( AEOLUS_ROOT."/app/$module/helper/$helper.php" );
   }
?>
