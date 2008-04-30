<?php
  /**
   * AeolusFront class
   *
   * Front controller to handle HTTP request
   *
   * @author Kinch Zhang <kinch.zhang@gmail.com>
   *
   */

  class AeolusFront
  {
    /**
	 * HTTP request
	 *
	 */
	private $request;

	/**
	 * Processing result
	 *
	 */
	private $result;

	/**
	 * Status code
	 *
	 */
	private $status;
    
	/**
	 * Constructor
	 *
	 * @param void
	 *
	 */
	function __construct()
	{
	  $this->request = strtolower($_SERVER['REQUEST_URI']);
	  $this->result = array();
	  $this->status= 200;
	}

	/**
	 * Start the application
	 *
	 * @access public
	 * @param void
	 * @return void
	 *
	 */
	public function start()
	{
	  $this->process();
	  $this->launch();
	}

	/**
	 * Process the HTTP request
	 *
	 * @access private
	 * @param void
	 * @return void
	 *
	 */
	private function process()
	{
	  # Remove the base url from the request
	  $cmd = substr($this->request,strlen(APP_BASE));
	  $cmd = trim($cmd,'/\\');
	  $cmd = explode('/',$cmd);
	  echo 'Command: ';
      var_dump($cmd);
	  # Init.
	  $this->result['module'] = 'index';
	  $this->result['controller'] = 'index';
	  $this->result['action'] = 'index';
	  $this->result['argc'] = 0;
	  $this->result['argv'] = array();
	  
	  # Process the command according to its size
	  $size = count($cmd);
      echo "Size: $size";
	  switch($size){
	   case 1:	   
		 $this->parseSizeOne($cmd);
	     break;

	   case 2:
	     $this->parseSizeTwo($cmd);
		 break;

	   case 3:
	     $this->parseSizeThree($cmd);
	     break;

	   case 4:
	     $this->parseSizeFour($cmd);
		 break;

	   default:
	     $this->parseSizeDefault($cmd,$size);
	     break;
	  }	 
    }

	/**
	 * Launch the application controllers
	 *
	 * @access private
	 * @param void
	 * @return void
	 *
	 */
	private function launch()
	{
	  echo '<br/><br/>Request: ';
	  var_dump($this->request);
	  echo 'Result: ';
	  var_dump($this->result);
	  echo 'Status ';
	  var_dump($this->status);
	}
	
	/**
     * Parse the one-size command
     * 
     * @access private
     * @param array $cmd the command array
     * @return void
     * 
     */
	private function parseSizeOne($cmd)
	{
	  if( $cmd[0] != '' ){
		   # First, check if it's a module
           if( $this->isModule($cmd[0]) ){
		     $this->result['module'] = $cmd[0];
			 return;
		   }

           # Then, chekc if it's a controller in 'index' module
		   if( $this->hasController('index',$cmd[0])){
		       $this->result['controller'] = $cmd[0];
			   return;
		   }else{
               # Neither a module, nor a controller in 'index' module
		       $this->status= 401;
		   }
		 }
	
	 }
	 
	 /**
      * Parse the two-size command
      * 
      * @access private
      * @param array $cmd the command array
      * @return void
      * 
      */
	 private function parseSizeTwo($cmd)
	 {
	   # First, check if it's a module
       if( $this->isModule($cmd[0])){ 
         # Check if the controller exists in this module
         if($this->hasController($cmd[0],$cmd[1]) ){
           $this->result['module'] = $cmd[0];
           $this->result['controller'] = $cmd[1];
           return;
         }else{
           # Controller not exists in this module
           $this->status= 402;
           return;
         }
       }

       # Then,check if it's a controller in 'index' module
       if( $this->hasController('index',$cmd[0]) ){
         $this->result['controller'] = $cmd[0];
         $this->result['action'] = $cmd[1];
         return;
       }else{
         # Controller not exists in 'index' module
         $this->status = 403;
       }
	 }
	 
	 /**
      * Parse three-size command
      * 
      * @access private
      * @param array $cmd the command array
      * @return void
      * 
      */
	 private function parseSizeThree($cmd)
	 {
	   # First, check if it's a module
	   if( $this->isModule($cmd[0])){
	     if( $this->hasController($cmd[0],$cmd[1])){
	       $this->result['module'] = $cmd[0];
	       $this->result['controller'] = $cmd[1];
	       $this->result['action'] = $cmd[2];
	       return;
	     }else{
	       # Controller not exists in this module
	       $this->status = 402;
	     }
	   }
	   
	   # Then, check if it's a controller in 'index' module
	   if( $this->hasController('index',$cmd[0])){
	     $this->result['controller'] = $cmd[0];
	     $this->result['action'] = $cmd[1];
	     $this->result['argc'] = 1;
	     $this->result['argv'][] = $cmd[2];
	     return;
	   }else{
	     # Controller not exists in 'index' module
         $this->status = 403;
	   }
	 }
	 
	 /**
      * Parse four-size command
      * 
      * @access private
      * @param array $cmd the command array
      * @return void
      * 
      */
	 private function parseSizeFour($cmd)
	 {
	   # First, check if it's a module
       if( $this->isModule($cmd[0])){
         if( $this->hasController($cmd[0],$cmd[1])){
           $this->result['module'] = $cmd[0];
           $this->result['controller'] = $cmd[1];
           $this->result['action'] = $cmd[2];
           $this->result['argc'] = 1;
           $this->result['argv'][] = $cmd[3];
           return;
         }else{
           # Controller not exists in this module
           $this->status = 402;
         }
       }
       
       # Then, check if it's a controller in 'index' module
       if( $this->hasController('index',$cmd[0])){
         $this->result['controller'] = $cmd[0];
         $this->result['action'] = $cmd[1];
         $this->result['argc'] = 2;
         $this->result['argv'][] = $cmd[2];
         $this->result['argv'][] = $cmd[3];
         return;
       }else{
         # Controller not exists in 'index' module
         $this->status = 403;
       }
	 }
	 
	 /**
      * Parse default-size command
      * 
      * @access private
      * @param array $cmd the command array
      * @param int $size the size of the command array
      * @return void
      *
      */
	 private function parseSizeDefault($cmd,$size)
	 {
	   # First, check if it's a module
       if( $this->isModule($cmd[0])){
         if( $this->hasController($cmd[0],$cmd[1])){
           $this->result['module'] = $cmd[0];
           $this->result['controller'] = $cmd[1];
           $this->result['action'] = $cmd[2];
           $this->result['argc'] = $size - 3;
           
           for( $i=3; $i<$size; $i++ ){
             $this->result['argv'][] = $cmd[$i];
           }
           return;
         }else{
           # Controller not exists in this module
           $this->status = 402;
         }
       }
       
       # Then, check if it's a controller in 'index' module
       if( $this->hasController('index',$cmd[0])){
         $this->result['controller'] = $cmd[0];
         $this->result['action'] = $cmd[1];
         $this->result['argc'] = $size - 2;
         
         for( $i=2; $i<$size; $i++ ){
           $this->result['argv'][] = $cmd[$i];
         }
         return;
       }else{
         # Controller not exists in 'index' module
         $this->status = 403;
       }
	 }
      
	

	/**
	 * Check if the module exists
	 *
	 * @access private
	 * @param string $module module name
	 * @return boolean true|false true if the module exists
	 *
	 */
	private function isModule($module)
	{
	  clearstatcache();
	  $path = AEOLUS_HOME."/app/$module";
	  return file_exists( $path );
	}

	/**
	 * Check if a given module has a given controller
	 *
	 * @access private
	 * @param string $module module name
	 * @param string $controller controller name
	 * @return boolean true|false true if the controller exists
	 *
	 */
	private function hasController($module,$controller)
	{
	  clearstatcache();
	  $path = AEOLUS_HOME."/app/$module/controller/$controller.php";
	  return file_exists( $path );
	}
  }

?>