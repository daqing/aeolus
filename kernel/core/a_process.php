<?php
  /**
   * Process the URL
   *
   * @param $url string the URL to route
   * @return $result array the routing result
   *
   */

   function a_process($url)
   {
	 # Remove the subdirectory part of an URL
	 $url = substr(strtolower($url),strlen(APP_BASEURL)+1);
	 $url = rtrim($url,'/\\');

	 # Get an array of all segments of an URL
	 $segment = explode('/',$url);
     
	 # Start validating the URL based on the number of segments
	 $number = count($segment);
	 
	 # Set default result as an error one
	 $result['module'] = 'error';
	 $result['controller'] = 'index';
	 $result['action'] = 'index';
	 $result['argc'] = 0;
	 $result['argv'] = array();
     
	 # Security check
	 if( strpos($url,'.') ){
	   return $result;
	 }
     
	 # Continue if the URL is valid
	 switch($number){
	   case 1:
		  # In this case, the first segment must be a valid module
		  # or, the client is requesting for the home page.
         if( $url == '' || $url == '/' ){
		   $result['module'] = 'index';
		 }else{
		   # First,check if this is a controller in 'index' module
		   if( a_is_index_ctlr($segment[0]) ){
		     $result['module'] = 'index';
		     $result['controller'] = $segment[0];
		   }else{
             # check if this a module
			 if( a_is_module($segment[0])){
			   $result['module'] = $segment[0];
			 }
		   }
		 }

	     break;
	   
	   case 2:
	     # In this case,the first segment can be a module name(A) or
		 # a controller name(B). if (A) and the module is valid, we'll
		 # call the 'index' function of that controller in that module
		 # If (B), we'll cal that function of that controller in index
		 # module.
		 if( a_is_module($segment[0])){
		   $result['module'] = $segment[0];
		   $result['controller'] = $segment[1];
		 }elseif( a_is_index_ctlr($segment[0])){
		   $result['controller'] = $segment[0];
		   $result['action'] = $segment[1];
		 }

	     break;

	   case 3:
	     # a '/module/controller/action' type or
		 # a '/controller/action/arg0' type
		 if( a_is_module($segment[0])){
		   $result['module'] = $segment[0];
		   $result['controller'] = $segment[1];
		   $result['action'] = $segment[2];
		 }elseif( a_is_index_ctlr($segment[0])){
		   $result['controller'] = $segment[0];
		   $result['action'] = $segment[1];
		   $result['argc'] = 1;
		   $result['argv'][] = $segment[2];
		 }

	     break;

	   case 4:
	     # a '/module/controller/action/param' type or
		 # a '/controller/action/arg0/arg1' one
		 if( a_is_module($segment[0]) ){
		   $result['module'] = $segment[0];
		   $result['controller'] = $segment[1];
		   $result['action'] = $segment[2];
		   $result['argc'] = 1;
		   $result['argv'][] = $segment[3];
		 }elseif( a_is_index_ctlr($segment[0])){
		   $result['controller'] = $segment[0];
		   $result['action'] = $segment[1];
		   $result['argc'] = 2;
		   $result['argv'][] = $segment[2];
		   $result['argv'][] = $segment[3];
		 }

		 break;

	   default:
	     # a '/module/controller/action/arg0/arg1/arg2....' type or
		 # a '/controller/action/arg0/arg1/arg2....' one
		 if( a_is_module($segment[0]) ){
		   $result['module'] = $segment[0];
		   $result['controller'] = $segment[1];
		   $result['action'] = $segment[2];
		   $result['argc'] = $number - 3;
           
		   for( $i=3; $i<$number; $i++ ){
		     $result['argv'][] = $segment[$i];
		   }

		 }elseif( a_is_index_ctlr($segment[0])){
		   $result['controller'] = $segment[0];
		   $result['action'] = $segment[1];
		   $result['argc'] = $number - 2;

		   for( $i=2; $i<$number; $i++ ){
		     $result['argv'][] = $segment[$i];
		   }
		 }

	     break;
	   }

     $_SESSION['aeolus']['result'] = $result;

	 return $result;
   }

   # Check if the request segment is a controller in 'index' module
   function a_is_index_ctlr($ctlr)
   {
     clearstatcache();
     return file_exists( AEOLUS_ROOT."/app/index/controller/$ctlr.php" );
   }

   # Check if the request segment is a module
   function a_is_module($module)
   {
     clearstatcache();
     return file_exists( AEOLUS_ROOT."/app/$module" );
   }

?>
