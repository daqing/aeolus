<?php
    /*
     * View class
     */

    class AeoView
    {
        public $title = 'Index';
    	
    	public $data = null;
  
        /* Render a given template */
        public function show()
        {	
            ob_start();
      	    require A_PREFIX . 'public/theme/' . APP_TPL . '.php';
            ob_end_flush();
        }
      	
        public function show_style()
        {
            echo SUB_DIR . 'public/theme/' . APP_STYLE . '.css';
        }
          
        public function show_jquery()
        {
            echo SUB_DIR . 'public/script/jquery-1.3.2.js';
        }
          
        public function show_jcorner()
        {
            echo SUB_DIR . 'public/script/jquery.corner-1.92.js';
        }  
  
        public function show_top()
    	{
    	    Aeolus::loadHelper('top', 'index');
    	    
            top();
    	}
    
    	public function show_nav()
    	{
    	    Aeolus::loadHelper('nav', 'index');
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
