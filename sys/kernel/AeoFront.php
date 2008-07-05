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
	  if (APP_DEBUG)
	    $this->result['inter'] = array();
	}

	public function run()
	{
	  $this->process();
	  $this->launch();
	}

	/* Process the HTTP request */
	private function process()
	{
	  /* Remove base url from the request */
	  $this->request = substr($this->request, strlen(URL_BASE));
	  $this->request = trim($this->request, '/\\');

	  if (strpos($this->request, '(') || strpos($this->request, '%'))
		return;

      /* Get segments */
	  $seg = (strlen($this->request)) ? explode('/', $this->request) : '/';

	  if ('/' !== $seg && is_array($seg)) {
	    require A_PREFIX.'etc/group.php';

		if (APP_DEBUG) {
		  $this->result['inter']['seg'] = $seg;
		  $this->result['inter']['grp'] = $group;
		}

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
	  $path = A_PREFIX . "app/$group/controller/$controller.php";    

      if (file_exists($path)) {
	    require 'Aeo.php';

	    /* Setup environment variable */
	    global $thisgrp;
	    $thisgrp = $group;

		/* Load controller */
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
	  echo '<div style="background-color:#EEE;border:1px solid #CCC;">';
	  echo '<h3 style="margin:10px;">AEOLUS DEBUG</h3>';
	  echo '<div style="margin:10px;border-top:1px solid #CCC;">';

	  echo '<h4>Base URL:';
	  echo '<span style="font-style:italic;color:#666;padding:0px 10px;">';
	  echo URL_BASE.'</span></h4>';

	  echo '<h4>Request segments:</h4>';
	  echo '<div style="background-color:#F7F7F7;padding:10px;">';
	  echo '<p>&nbsp;Group:&nbsp;<i>'.$this->result['group'].'</i></p>';
	  echo '<p>&nbsp;Controller:&nbsp;<i>'.$this->result['controller'];
	  echo '</i></p><p>&nbsp;Arguments:&nbsp;';
	  if (count($this->result['argv']) > 0) {
	   foreach ($this->result['argv'] as $v) {
	    echo '&nbsp;<span style="font-style:italic;">\'';
	    echo $v.'\'</span>&nbsp;';
	   }
	  } else
	      echo '<span style="font-style:italic;">null</span>';
	  echo '</p></div>';
	    
      echo '<h4>Valid groups:</h4>';
	  echo '<p style="padding:10px;background-color:#F7F7F7;">';
	  foreach ($this->result['inter']['grp'] as $v) {
	    echo '&nbsp;<span style="font-style:italic;">\'';
	    echo $v.'\'</span>&nbsp;';
	  }
	  echo '</p></div></div>';
	} 

	private function to_home()
	{
	  header('Location: ' . URL_BASE );
	  exit(0);
	}
  }
?>
