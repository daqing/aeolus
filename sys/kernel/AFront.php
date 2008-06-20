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
	  $this->result['argv'] = array();
	  $this->result['inter'] = array();
	}

	/**
	 * Run the application
	 *
	 * @access public
	 * @param void
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
	 */
	private function process()
	{
	  # Remove base url from the request
	  $this->request = substr($this->request, strlen(URL_BASE));
	  $this->request = trim($this->request, '/\\');

	  if (strpos($this->request, '(') || strpos($this->request, '%'))
		return;

      # Get segments
	  $seg = (strlen($this->request)) ? explode('/', $this->request) : '/';

	  if ('/' !== $seg && is_array($seg)) {
	    # Load valid groups
	    require A_PREFIX.'etc/group.php';
	    
	    # Set intermedia data for debugging
	    $this->result['inter']['seg'] = $seg;
		$this->result['inter']['grp'] = $group;

		$size = count($seg);
		switch ($size) {
		  case 1:
			if (in_array($seg[0], $group)) {
			  # Group defined
			  $this->result['group'] = $seg[0];
			}
			else{
			  # Controller in 'index' group
			  $this->result['controller'] = $seg[0];
			}

		    break;

		  case 2:
		    if (in_array($seg[0], $group)) {
			  # Group defined
			  $this->result['group'] = $seg[0];
			  $this->result['controller'] = $seg[1];
			}
			else{
			  # Controller in 'index' group
			  $this->result['controller'] = $seg[0];
			  $this->result['argv'][] = $seg[1];
			}

		    break;
		  
		  default:
		    if (in_array($seg[0], $group)) {
			  # Group defined
			  $this->result['group'] = $seg[0];
			  $this->result['controller'] = $seg[1];
			  $this->result['argv'] = array_slice($seg, 2);
			}
			else{
			  # Controller in 'index' group
			  $this->result['controller'] = $seg[0];
			  $this->result['argv'] = array_slice($seg, 1);
			}

		    break;
		}
	    
      }
	  
    }

	/**
	 * Launch application controllers
	 *
	 * @access private
	 * @param array $seg Segment array(for debugging)
	 */
	private function launch()
	{
	  # Flag
	  $launched = false;

	  extract($this->result);
	  $path = A_PREFIX . "app/$group/controller/$controller.php";    

      if (file_exists($path)) {
	    # Load Assistant class
	    require 'A.php';
	    
	    # Setup environment variable
	    global $thisgrp;
	    $thisgrp = $group;

		# Load controller
	    require($path);
	    if (function_exists($controller)) {
	      # Launch this controller
		  $launched = true;
          $controller($this->result['argv']);
		}
	  }

	  if (! $launched)
	    (APP_DEBUG) ? $this->debug() : $this->to_home();
	}

	/**
	 * Show debug info
	 *
	 * @access private
	 */
	private function debug()
	{
	  echo '<div style="background-color:#EEE;border:1px solid #CCC;">';
	  echo '<h3 style="margin:10px;">AEOLUS DEBUG</h3>';
	  echo '<div style="margin:10px;border-top:1px solid #CCC;">';

	  # Subdir
	  echo '<h4>Application subdirectory:';
	  echo '<span style="font-style:italic;color:#666;padding:0px 10px;">\'';
	  echo SUB_DIR.'\'</span></h4>';

	  # Base URL
	  echo '<h4>Base URL:';
	  echo '<span style="font-style:italic;color:#666;padding:0px 10px;">\'';
	  echo URL_BASE.'\'</span></h4>';

	  # Request segments
	  echo '<h4>Request segments:</h4>';
	  echo '<div style="background-color:#F7F7F7;padding:0px 10px;border:1px solid #CCC;">';
	  echo '<p>&nbsp;Group:&nbsp;<i>'.$this->result['group'].'</i></p>';
	  echo '<p>&nbsp;Controller:&nbsp;<i>'.$this->result['controller'].'</i></p>';
	  echo '<p>&nbsp;Arguments:&nbsp;';
	  if (count($this->result['argv']) > 0) {
	   foreach ($this->result['argv'] as $v) {
	    echo '<span style="font-style:italic;padding:0px 5px;border:1px dashed #999;">';
	    echo $v.'</span>&nbsp;&nbsp;';
	   }
	  }
	  else
	    echo '<span style="font-style:italic;">null</span>';

	  echo '</p></div>';
	    
	  # Valid groups
      echo '<h4>Valid groups:</h4>';
	  echo '<p style="padding:10px;background-color:#F7F7F7;border:1px solid #CCC;">';
	  foreach ($this->result['inter']['grp'] as $v) {
	    echo '<span style="font-style:italic;padding:0px 5px;border:1px dashed #999;">';
	    echo $v.'</span>&nbsp;&nbsp;';
	  }

	  echo '</p></div></div>';
	} 

	/**
	 * Redirect to the home page
	 *
	 * @access private
	 */
	private function to_home()
	{
	  header('Location: ' . URL_BASE );
	  exit(0);
	}
  }
?>
