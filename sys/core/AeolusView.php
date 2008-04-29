<?php
    /**
     * Aeolus View class
     * 
     * @category kernel
     * @author Qingcheng Zhang <kinch.zhang@gmail.com>
     * @copyright Copyright (c) 2008-2009, CityGeneration, Inc. (http://www.citygeneration.com)
     * 
     * Subversion keywords
     * 
     * $LastChangedDate$
     * $LastChangedBy$
     * $LastChangedRevision$
     * $URL$
     * $Id$
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
		 * Set data to render
		 *
		 */
		function set_data($data)
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
			
			require( AEOLUS_ROOT.'/pub/theme/'.APP_THEME.'/'.APP_THEME.'.php');
						
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
		  echo APP_SUBDIR.'/pub/js/app/default.js';
		}

		function render_header()
		{
		   echo '<div id="message">';
		   echo '</div>';
		   echo '<a href="'.APP_BASEURL.'/"><img src="'.APP_SUBDIR.'/pub/img/aeolus.gif"';
		   echo ' alt="Aeolus" /></a>';
		  
		}
        
        function render_theme()
        {
            echo APP_SUBDIR.'/pub/theme/'.APP_THEME.'/'.APP_THEME.'.css';
        }
        
        function render_jquery()
        {
            echo APP_SUBDIR.'/pub/js/core/jquery-1.2.3.min.js';
        }
        
        function render_jquery_corner()
        {
            echo APP_SUBDIR.'/pub/js/core/jquery.corner-1.92.js';
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
