<?php
  /*
   * Front controller
   */

  class AeoFront
  {
	private $request;

	private $result;

	function __construct()
	{
	  $this->request = strtolower($_SERVER['REQUEST_URI']);

	  $this->result['group'] = 'index';
	  $this->result['controller'] = 'index';
	  $this->result['argv'] = array();
	}

	public function run()
	{
	  $this->process();
	  $this->launch();
	}

	/* Process the HTTP request */
	private function process()
	{
	  /* Remove output base url from the request */
	  $this->request = substr($this->request, strlen(OP_BASE));
	  $this->request = trim($this->request, '/\\');

	  if (strpos($this->request, '(') || strpos($this->request, '%'))
		return;

      /* Get segments */
	  $seg = (strlen($this->request)) ? explode('/', $this->request) : '/';

	  if ('/' !== $seg && is_array($seg)) {
	    require A_PREFIX.'config/system/group.php';

		$size = count($seg);
		switch ($size) {
		case 1:
		  if (in_array($seg[0], $group))
		    $this->result['group'] = $seg[0];
		  else
		    $this->result['controller'] = $seg[0];
		break;
		case 2:
		  if (in_array($seg[0], $group)) {
		    $this->result['group'] = $seg[0];
		    $this->result['controller'] = $seg[1];
		  } else {
		    $this->result['controller'] = $seg[0];
		    $this->result['argv'][] = $seg[1];
		  }
		break;
		default:
		  if (in_array($seg[0], $group)) {
		    $this->result['group'] = $seg[0];
			$this->result['controller'] = $seg[1];
			$this->result['argv'] = array_slice($seg, 2);
		  } else {
		    $this->result['controller'] = $seg[0];
		    $this->result['argv'] = array_slice($seg, 1);
		  }
		break;
		}
      }
    }

	/* Launch application controllers */
	private function launch()
	{
	  $launched = false;

	  extract($this->result);
	  $path = A_PREFIX . "module/$group/controller/$controller.php";    

      if (file_exists($path)) {
	    require 'Aeolus.php';

	    /* Setup environment variable */
	    global $thisgrp;
	    $thisgrp = $group;

		/* Load controller */
        // TODO: use output buffer?
	    require($path);

	    if (function_exists($controller)) {
		  $launched = true;
          $controller($this->result['argv']);
		}
	  }

	  if (!$launched)
	    (APP_DEBUG) ? $this->debug() : $this->to_home();
	}

	private function debug()
	{
	  extract($this->result);
	  if ('index' == $group) {
	    echo "Fatal: '$controller' is neither a valid group nor a ";
		echo "controller in 'index' group.";
	  } else {
	    echo "Fatal: controller '$controller' not found in '$group' group.";
	  }
	} 

	private function to_home()
	{
	  header('Location: ' . SUB_URL );
	  exit(0);
	}
  }
?>
