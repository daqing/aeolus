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
    	 * Theme name
    	 *
    	 */
    	var $theme = 'default';	

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
			
			$template_path = AEOLUS_ROOT.'/static/template/'.$this->theme .'.php';
			require($template_path);
						
			ob_end_flush();
    	}

		/**
		 * Render a template
		 *
		 */
		function render_template($module,$template)
		{
		  $template_path = AEOLUS_ROOT."/static/template/$module/$template.php";
		  
		  if( file_exists($template_path) ){
            # parse the template
            require($template_path);

		  }else{
		    # Error: template not exists
			die("Fatal error: the template -- $template_path -- does not exist.<br/>System exited.");
		  }
		}

    
    	/**
    	 * Set theme
    	 *
    	 * @access private
    	 */
    	function set_theme($name = 'default')
    	{
    	    $this->theme = $name;
    	}

    	/**
		 * Render JavaScript
		 *
		 */
		function get_javascript()
		{
		}		
        
		function get_logo_area()
		{
		   echo '<div id="message">';
		   echo '</div>';
		   echo '<a href="'.AEOLUS_OUTPUT.'/"><img src="'.AEOLUS_BASEURL.'/static/res/aeolus.gif"';
		   echo ' alt="Aeolus" /></a>';
		  
		}
        
        function get_theme()
        {
            echo AEOLUS_BASEURL.'/static/template/'.$this->theme.'.css';
        }
        
        function get_jquery()
        {
            echo AEOLUS_BASEURL.'/static/script/jquery-1.2.3.min.js';
        }
        
        function get_jquery_corner()
        {
            echo AEOLUS_BASEURL.'/static/script/jquery.corner-1.92.js';
        }	
	
		# Render spotlight
		function get_spotlight()
		{
		    echo '<strong>Spotlight</strong>';
		}
    
		function get_control_panel()
		{
		  echo '<p>This is the control panel'; 
		}
    	/**
    	 * Render content
    	 *
    	 */
    	function get_sections()
    	{
    	  echo '<div class="section">';
          echo '<p>This is a section<br/>';
		  echo 'And the Base output URL is '. AEOLUS_OUTPUT.'</p>';
		  if(! $_SESSION['aeolus']['can_rewrite'] ){
		      echo '<span style="color:red">You don\'t have mod_rewrite enabled or don\'t allow .htaccess file to override the default settings.</span>';
		  }
    	  echo '</div>';
    	}
    }
?>
