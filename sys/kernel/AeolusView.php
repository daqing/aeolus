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
    	function __construct(){}
        
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
			
			require( AEOLUS_HOME.'/pub/theme/'.APP_THEME.'.php');
						
			ob_end_flush();
    	}

		function showIncludedScript()
		{
		  echo APP_SUB.'/pub/script/app/default.js';
		}

		function showHeader()
		{
		   echo '<div id="message">';
		   echo '</div>';
		   echo '<a href="'.APP_BASE.'/"><img src="'.APP_SUB.'/pub/image/aeolus.gif"';
		   echo ' alt="Aeolus" /></a>';
		  
		}
        
        function showTheme()
        {
            echo APP_SUB.'/pub/theme/'.APP_THEME.'.css';
        }
        
        function showJquery()
        {
            echo APP_SUB.'/pub/script/core/jquery-1.2.3.min.js';
        }
        
        function showJqueryCorner()
        {
            echo APP_SUB.'/pub/script/core/jquery.corner-1.92.js';
        }	
	
	    function showScript(){}

		function showNavigator(){}

    
		function showsidebar(){}

		function showNotice(){}

    	function showContent(){}
    }
?>
