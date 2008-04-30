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
	 * Run the application
	 *
	 * @access public
	 * @param void
	 * @return void
	 *
	 */
	public function run()
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
		 require( 'AeolusRouterOne.php' );
	     break;

	   case 2:
	     require( 'AeolusRouterTwo.php' );
		 break;

	   case 3:
		 require( 'AeolusRouterThree.php' );
	     break;

	   case 4:
		 require( 'AeolusRouterFour.php' );
		 break;

	   default:
		 require( 'AeolusRouterDefault.php' );
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
	  if( 200 == $this->status ){
	    extract($this->result);
		if( 'ajax' != $module ){
		  require( 'kernel/AeolusFactory.php' );
		}

	    $path = AEOLUS_HOME."/app/$module/controller/$controller.php";
		require( $path );
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
