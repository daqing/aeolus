<?php
    /**
     * AeolusView class
     * 
     * @author Qingcheng Zhang <kinch.zhang@gmail.com>
     * 
     */

	class AeolusView
	{
    	/**
    	 * Page title
    	 *
    	 * @access public
    	 *
    	 */
    	var $title = 'Index';
		
		/**
		 * Data to be displayed
		 *
		 */
		var $data = null;

    
        /**
    	 * Constructor
    	 *
    	 */
    	function View(){}
        
		/**
		 * Set data for template to use
		 *
		 */
		function setData($data)
		{
		  $this->data = $data;
		}

    	/**
    	 * Render a given template
    	 *
    	 */
    	function show()
    	{	
            ob_start();
			
			require( AEOLUS_HOME.'/pub/theme/'.APP_THEME.'/'.APP_THEME.'.php');
						
			ob_end_flush();
    	}

		/**
		 * Render inlice Javascript
		 *
		 */
	    function render_js(){}

		/**
		 * render Included javascript file
		 *
		 * @param $name name of the javascript file
		 * @return $path path to the javascript file
		 */
		function render_included_js()
		{
		  echo APP_SUB.'/pub/js/app/default.js';
		}

		function render_header()
		{
		   echo '<div id="message">';
		   echo '</div>';
		   echo '<a href="'.APP_BASE.'/"><img src="'.APP_SUB.'/pub/image/aeolus.gif"';
		   echo ' alt="Aeolus" /></a>';
		  
		}
        
        function render_theme()
        {
            echo APP_SUB.'/pub/theme/'.APP_THEME.'/'.APP_THEME.'.css';
        }
        
        function render_jquery()
        {
            echo APP_SUB.'/pub/js/core/jquery-1.2.3.min.js';
        }
        
        function render_jquery_corner()
        {
            echo APP_SUB.'/pub/js/core/jquery.corner-1.92.js';
        }	
	
		# Render spotlight
		function render_spotlight(){}
    
		function render_sidebar(){}

    	/**
    	 * Render content
    	 *
    	 */
    	function render_content(){}
    }
?>
