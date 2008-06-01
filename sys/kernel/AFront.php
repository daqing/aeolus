<?php
  /**
   * AFront class
   *
   * Front controller to handle HTTP request
   *
   * @author Kinch Zhang <kinch.zhang@gmail.com>
   */

  class AFront
  {
    /**
	 * HTTP request
	 *
	 * @access private
	 */
	private $request;
	
	/**
	 * Processing result
	 *
	 * @access private
	 */
	private $result;

	/**
	 * Constructor
	 *
	 */
	function __construct()
	{
	  $this->request = strtolower($_SERVER['REQUEST_URI']);
	  $this->result = array();

	  # Init.
	  $this->result['group'] = 'index';
	  $this->result['controller'] = 'index';
	  $this->result['argc'] = 0;
	  $this->result['argv'] = array();
	}

	/**
	 * Run the application
	 *
	 * @access public
	 * @param void
	 */
	public function run()
	{
	  $this->launch($this->process());
	}

	/**
	 * Process the HTTP request
	 *
	 * @access private
	 * @param void
	 */
	private function process()
	{
	  # Remove the base url from the request
	  $this->request = substr($this->request,strlen(APP_PREFIX));
	  $this->request = trim($this->request,'/\\');

	  if( strpos($this->request,'(') || strpos($this->request,'%')){
	    # Invalid request
		return;
	  }

      # Get segments
	  if( strlen($this->request) > 0 ){
	    $seg = explode('/',$this->request);
	  }else{
	    $seg = '/';
	  }

	  if( '/' !== $seg && is_array($seg) ){
	    # Load valid groups
	    require AEOLUS_HOME.'/etc/group.php';

		$size = count($seg);
		switch( $size ){

		  case 1:
			if( in_array($seg[0], $group)){
			  # Group defined
			  $this->result['group'] = $seg[0];
			}else{
			  # Controller in 'index' group
			  $this->result['controller'] = $seg[0];
			}
		    break;

		  case 2:
		    if( in_array($seg[0], $group) ){
			  # Group defined
			  $this->result['group'] = $seg[0];
			  $this->result['controller'] = $seg[1];
			}else{
			  # Controller in 'index' group
			  $this->result['controller'] = $seg[0];
			  $this->result['argc'] = 1;
			  $this->result['argv'][] = $seg[1];
			}
		    break;
		  
		  default:
		    if( in_array($seg[0], $group) ){
			  # Group defined
			  $this->result['group'] = $seg[0];
			  $this->result['controller'] = $seg[1];
			  $this->result['argc'] = $size - 2;
			  $this->result['argv'] = array_slice($seg, 2);
			}else{
			  # Controller in 'index' group
			  $this->result['controller'] = $seg[0];
			  $this->result['argc'] = $size - 1;
			  $this->result['argv'] = array_slice($seg, 1);
			}
		    break;
		}
      }
	  return $seg;
    }

	/**
	 * Launch application controllers
	 *
	 * @access private
	 * @param array $seg Segment array(for debugging)
	 */
	private function launch($seg)
	{
		# Load factory methods
	    require( 'A.php' );
	    
		# Setup environment variable
		global $thisgrp;
		$thisgrp = $this->result['group'];

	    extract($this->result);
	    $path = AEOLUS_HOME."/app/$group/controller/$controller.php";    

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
			if( APP_DEBUG ){
			  # Error: controller not defined
			  $error = "Fatal: function <i>'$controller'</i> not defined in <i>'$group'</i> group";
			  die($error);
			}else{
			  # Redirect to home page
			  header('Location: /');
			}
		  }

		}else{
		  if( APP_DEBUG ){
			require( AEOLUS_HOME.'/etc/group.php');
			if(in_array($seg[0], $group)){
			  # Error: controller not found
			  $error = "Fatal: Controller <i>'$controller'</i> not found in <i>'$seg[0]'</i> group";
			  die($error);
			}else{
			  if( file_exists( AEOLUS_HOME."/app/$seg[0]")){
			    # Error: group exists in app but not defined
				$error = "Fatal: group '$seg[0]' exists but not defined";
			    die($error);
			  }else{
			  	$error = "Fatal: group <i>'$seg[0]'</i> not defined and controller <i>'$controller'</i> ";
			  	$error .= 'not found in <i>\'index\'</i> group';
			    die($error);
		      }
			}
		  }else{
		    # Redirect to home page
		    header('Location: /');
		  }
		}
	}

  }

?>
