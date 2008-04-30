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
     * The command to process
     * 
     */
	private $cmd;

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
	  $this->cmd = null;
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
	  $this->cmd = substr($this->request,strlen(APP_BASE));
	  $this->cmd = trim($this->cmd,'/\\');
	  $this->cmd = explode('/',$this->cmd);
	  
	  # Init.
	  $this->result['module'] = 'index';
	  $this->result['controller'] = 'index';
	  $this->result['action'] = 'index';
	  $this->result['argc'] = 0;
	  $this->result['argv'] = array();
	  
	  # Process the command according to its size
	  $size = count($this->cmd);
      
	  switch($size){
	   case 1:	   
		 $this->parseSizeOne();
	     break;

	   case 2:
	     $this->parseSizeTwo();
		 break;

	   case 3:
	     $this->parseSizeThree();
	     break;

	   case 4:
	     $this->parseSizeFour();
		 break;

	   default:
	     $this->parseSizeDefault($size);
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
	  /*
	  echo '<br/><br/>Request: ';
	  var_dump($this->request);
	  echo 'Result: ';
	  var_dump($this->result);
	  echo 'Status ';
	  var_dump($this->status);
      */
	  if( 200 == $this->status ){
	    extract($this->result);
	    $path = AEOLUS_HOME."/app/$module/controller/$controller.php";
		AeolusFactory::loadOnce($path);
		if( function_exists( $action ) ){
		  if( $this->result['argc'] > 0 ){
		    $action($this->result['argv']);
		  }else{
		    $action();
		  }
		}else{
		  $this->status = 403;
		  $this->showError();
		}
	  }else{
	    $this->showError();
	  }
	}
	
	/**
     * Parse the one-size command
     * 
     * @access private
     * @param void
     * @return void
     * 
     */
	private function parseSizeOne()
	{
	  $cmd = $this->cmd;
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
      * @param void
      * @return void
      * 
      */
	 private function parseSizeTwo()
	 {
	   $cmd = $this->cmd;
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
      * @param void
      * @return void
      * 
      */
	 private function parseSizeThree()
	 {
	   $cmd = $this->cmd;
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
      * @param void
      * @return void
      * 
      */
	 private function parseSizeFour()
	 {
	   $cmd = $this->cmd;
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
      * @param int $size the size of the command array
      * @return void
      *
      */
	 private function parseSizeDefault($size)
	 {
	   $cmd = $this->cmd;
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

	/**
	 * Show errors accound to the status code
	 *
	 * @access private
	 * @param void
	 * @return void
	 *
	 */
	private function showError()
	{
	  if( APP_DEBUG ){
        switch( $this->status ){
		  case 401:
		    $error = '<h3>[ERROR 401] \''.$this->cmd[0];
			$error .= '\' IS NEITHER A MODULE NOR A CONTROLLER ';
			$error .= 'IN THE \'index\' MODULE</h3>';
			echo $error;
			break;
		  
		  case 402:
		    $error = '<h3>[ERROR 402] CONTROLLER \''.$this->cmd[1];
			$error .= '\' NOT FOUND IN THE \''.$this->cmd[0];
			$error .= '\' MODULE</h3>';
			echo $error;
			break;
		  
		  case 403:
		    $error = '<h3>[ERROR 403] FUNCTION \''.$this->cmd[2];
			$error .= '\' NOT DEFINED IN \''.AEOLUS_HOME.'/app/';
			$error .= $this->cmd[0].'/controller/';
			$error .= $this->cmd[1].'.php\'</h3>';
			echo $error;
			break;
		}

	  }else{
	    die('<h3>[ERROR 400] BAD REQUEST.</h3>');
	  }
	}
  }

?>
