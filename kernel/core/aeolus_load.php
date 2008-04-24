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
	     if( AEOLUS_DEBUG ){
	       echo '<br/>[ERROR] The file: '.$path;
		   echo 'you\'re trying to include doesn\'t exist';
		   die();
		 }else{
	       die('<br/>[ERROR] The file you\'re trying to include doesn\'t exist');
		 }
	   }
	 }
   }

   function app_helper_load($module,$helper)
   {
     aeolus_load( AEOLUS_ROOT."/app/$module/helper/$helper.php" );
   }
?>
