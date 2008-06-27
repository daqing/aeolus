<?php
  /*
   * AView class
   */

  class AView
  {
    public $title = 'Index';
  	
  	public $data = null;

    function __construct()
	{
	}
      
    /* Render a given template */
    public function show()
    {	
      ob_start();
  	  require A_PREFIX . 'pub/theme/' . APP_TPL . '.php';
      ob_end_flush();
    }
  	
    public function show_style()
    {
      echo SUB_DIR . 'pub/theme/' . APP_STYLE . '.css';
    }
      
    public function show_jquery()
    {
      echo SUB_DIR . 'pub/script/jquery-1.2.6.min.js';
    }
      
    public function show_jcorner()
    {
      echo SUB_DIR . 'pub/script/jquery.corner-1.92.js';
    }  

  	public function show_top()
  	{
  	  A::h('top', 'index');
  	  top();
  	}
  
  	public function show_nav()
  	{
  	  A::h('nav', 'index');
  	  nav();
  	}

    public function show_script()
	{
	}

  	public function show_sidebar()
	{
	}

    public function show_content()
	{
	}

  	public function html_safe($v)
  	{
  	  return htmlentities($v, ENT_QUOTES, 'UTF-8');
  	}
  }
?>
