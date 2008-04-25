<?php
    /**
     * View class
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

	class View
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
    	function View($data)
    	{
    	  $this->data = $data;
    	}
        
    	/**
    	 * Render a given template
    	 *
    	 */
    	function render()
    	{	
            ob_start();
			
			$template = AEOLUS_ROOT.'/pub/theme/'.APP_THEME.'/'.APP_THEME.'.php';
			require($template);
						
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

		function render_logo_area()
		{
		   echo '<div id="message">';
		   echo '</div>';
		   echo '<a href="'.AEOLUS_OUTPUT.'/"><img src="'.APP_SUBDIR.'/pub/img/aeolus.gif"';
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
		function render_spotlight()
		{
		    echo '<strong>Spotlight</strong>';
		}
    
		function render_control()
		{
		  echo '<p>This is the control panel'; 
		}
    	/**
    	 * Render content
    	 *
    	 */
    	function render_sections()
    	{
    	  echo '<div class="section">';
          echo '<p>This is a section<br/>';
		  echo 'And the output URL is '. AEOLUS_OUTPUT.'</p>';
		  if(! $_SESSION['aeolus']['can_rewrite'] ){
		      echo '<span style="color:red">You don\'t have mod_rewrite enabled or don\'t allow .htaccess file to override the default settings.</span>';
		  }
    	  echo '</div>';
    	}
    }
?>
