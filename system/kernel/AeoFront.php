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

	  $this->result['module'] = 'index';
	  $this->result['controller'] = 'index_index';
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
	    require A_PREFIX.'config/system/module.php';

		$size = count($seg);
		switch ($size) {
		case 1:
		  if (in_array($seg[0], $module)) {
              // default controller in this module
              $this->result['module'] = $seg[0];
              $this->result['controller'] = $seg[0] . '_index';
          } else {
              // this controller in 'index' module
              $this->result['controller'] = 'index_' . $seg[0];
          }
		break;
		case 2:
		  if (in_array($seg[0], $module)) {
              $this->result['module'] = $seg[0];
              $this->result['controller'] = $seg[0] . '_' .$seg[1];
		  } else {
              // this controller in 'index' group with argv
		      $this->result['controller'] = 'index_' . $seg[0];
		      $this->result['argv'][] = $seg[1];
		  }
		break;
		default:
		  if (in_array($seg[0], $module)) {
		      $this->result['module'] = $seg[0];
			  $this->result['controller'] = $seg[0] . '_' . $seg[1];
			  $this->result['argv'] = array_slice($seg, 2);
		  } else {
		      $this->result['controller'] = 'index_' . $seg[0];
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
	  $path = A_PREFIX . "module/$module/controller/$controller.php";    

      if (file_exists($path)) {
	    require 'Aeolus.php';

	    /* Setup environment variable */
	    global $thisGroup;
	    $thisGroup = $module;

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
	  if ('index' == $module) {
	    echo "Fatal: '$controller' is neither a valid module nor a ";
		echo "controller in 'index' module.";
	  } else {
	    echo "Fatal: controller '$controller' not found in '$module' module.";
	  }
	} 

	private function to_home()
	{
	  header('Location: ' . SUB_URL );
	  exit(0);
	}
  }
?>
