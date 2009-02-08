<?php
  /*
   * View class
   */

  class AeoView
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
      echo SUB_URL . 'pub/theme/' . APP_STYLE . '.css';
    }
      
    public function show_jquery()
    {
      echo SUB_URL . 'pub/script/jquery-1.2.6.min.js';
    }
      
    public function show_jcorner()
    {
      echo SUB_URL . 'pub/script/jquery.corner-1.92.js';
    }  

  	public function show_top()
  	{
  	  Aeolus::h('top', 'index');
  	  top();
  	}
  
  	public function show_nav()
  	{
  	  Aeolus::h('nav', 'index');
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
