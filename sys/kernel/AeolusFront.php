<?php if(! defined('APP_STARTED')){ die('<h3>BAD REQUEST.</h3>');}
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
	  $this->request = substr($this->request,strlen(APP_BASE));
	  $this->request = trim($this->request,'/\\');

	  if( strlen($this->request) > 0 ){
	    $this->cmd = explode('/',$this->request);
	  }else{
	    $this->cmd = '/';
	  }
	  
	  # Load valid modules
	  require AEOLUS_HOME.'/etc/module.php';

	  # Init.
	  $this->result['module'] = 'index';
	  $this->result['controller'] = 'index';
	  $this->result['argc'] = 0;
	  $this->result['argv'] = array();
	  
	  if( '/' !== $this->cmd && is_array($this->cmd) ){
		$size = count($this->cmd);

		switch( $size ){
		  case 1:
			if( in_array($this->cmd[0], $module)){
			  $this->result['module'] = $this->cmd[0];
			}else{
			  $this->result['controller'] = $this->cmd[0];
			}

		    break;

		  case 2:
		    if( in_array($this->cmd[0], $module) ){
			  $this->result['module'] = $this->cmd[0];
			  $this->result['controller'] = $this->cmd[1];
			}else{
			  $this->result['controller'] = $this->cmd[0];
			  $this->result['argc'] = 1;
			  $this->result['argv'][] = $this->cmd[1];
			}

		    break;
		  
		  default:
		    if( in_array($this->cmd[0], $module) ){
			  $this->result['module'] = $this->cmd[0];
			  $this->result['controller'] = $this->cmd[1];
			  $this->result['argc'] = $size - 2;

			  for( $i=0; $i < $this->result['argc']; $i++ ){
			    $this->result['argv'][] = $this->cmd[$i+2];
			  }
			}else{
			  $this->result['controller'] = $this->cmd[0];
			  $this->result['argc'] = $size - 1;

			  for( $i=0; $i < $this->result['argc']; $i++ ){
			    $this->result['argv'][] = $this->cmd[$i+1];
			  }
			}

		    break;
		} 
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
		# Load factory methods
	    require( 'AeolusFactory.php' );
	    
		# Setup environment variable
		global $thisModule;
		$thisModule = $this->result['module'];

	    extract($this->result);
	    $path = AEOLUS_HOME."/app/$module/controller/$controller.php";

		if( file_exists($path) ){
		  require( $path );

		  if( function_exists( $controller ) ){
		    # Launch this controller
		    if( $this->result['argc'] > 0 ){
		      $controller($this->result['argv']);
		    }else{
		      $controller();
		    }

		  }else{
		    # Controller functon not defined
			die('FATAL: Controller function not defined');
		  }

		}else{
		  # Controller file not found
		  die('FATAL: Controller file not found');
		}
	}

  }

?>
