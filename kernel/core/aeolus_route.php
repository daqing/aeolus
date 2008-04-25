<?php
  /**
   * Aeolus_route function
   *
   * @param $url string the URL to route
   * @return $result array the routing result
   *
   */

   function aeolus_route($url)
   {
     # Load valid modules(the $module array)
	 require( AEOLUS_ROOT.'/conf/app/valid_modules.php' );
	 # Remove the subdirectory part of an URL
	 $subdir = rtrim(AEOLUS_SUBDIR,'/\\');
	 if( !AEOLUS_CAN_REWRITE ){
	   $subdir .= 'index.php/';
	 }
     
	 $url = substr(strtolower($url),strlen($subdir)+1);
	 $url = rtrim($url,'/\\');

	 # Get an array of all segments of an URL
	 $segment = explode('/',$url);
     
	 # Start validating the URL based on the number of segments
	 $number = count($segment);
	 
	 # Set default result as an error one
	 $result['module'] = 'index';
	 $result['controller'] = 'error';
	 $result['action'] = 'index';
	 $result['argc'] = 0;
	 $result['argv'] = array();
     
	 switch($number){
	   case 1:
		  # In this case, the first segment must be a valid module
		  # or, the client is requesting for the home page.
         if( $url == '' || $url == '/' ){
		   $result['controller'] = 'index';
		 }elseif( in_array($segment[0],$module) ){
		   $result['module'] = $segment[0];
		   $result['controller'] = 'index';
		 }

	     break;
	   
	   case 2:
	     # In this case,the first segment can be a module name(A) or
		 # a controller name(B). if (A) and the module is valid, we'll
		 # call the 'index' function of that controller in that module
		 # If (B), we'll cal that function of that controller in index
		 # module.
		 if( in_array($segment[0],$module) ){
		   $result['module'] = $segment[0];
		   $result['controller'] = $segment[1];
		 }else{
		   $result['controller'] = $segment[0];
		   $result['action'] = $segment[1];
		 }

	     break;

	   case 3:
	     # a '/module/controller/action' type or
		 # a '/controller/action/arg0' type
		 if( in_array($segment[0],$module) ){
		   $result['module'] = $segment[0];
		   $result['controller'] = $segment[1];
		   $result['action'] = $segment[2];
		 }else{
		   $result['controller'] = $segment[0];
		   $result['action'] = $segment[1];
		   $result['argc'] = 1;
		   $result['argv'][] = $segment[2];
		 }

	     break;

	   case 4:
	     # a '/module/controller/action/param' type or
		 # a '/controller/action/arg0/arg1' one
		 if( in_array($segment[0],$module) ){
		   $result['module'] = $segment[0];
		   $result['controller'] = $segment[1];
		   $result['action'] = $segment[2];
		   $result['argc'] = 1;
		   $result['argv'][] = $segment[3];
		 }else{
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
		 if( in_array($segment[0],$module) ){
		   $result['module'] = $segment[0];
		   $result['controller'] = $segment[1];
		   $result['action'] = $segment[2];
		   $result['argc'] = $number - 3;
           
		   for( $i=3; $i<$number; $i++ ){
		     $result['argv'][] = $segment[$i];
		   }

		 }else{
		   $result['controller'] = $segment[0];
		   $result['action'] = $segment[1];
		   $result['argc'] = $number - 2;

		   for( $i=2; $i<$number; $i++ ){
		     $result['argv'][] = $segment[$i];
		   }
		 }

	     break;
	   }

	 return $result;
   }
?>
