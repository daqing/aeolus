<?php
    /**
     * AView class
     * 
     * @author Kinch Zhang <kinch.zhang@gmail.com>
     */

	class AView
	{
    	/**
    	 * Page title
    	 *
    	 * @access public
    	 */
    	public $title = 'Index';
		
		/**
		 * Data to display
		 *
		 * @access public
		 */
		public $data = array();

    
        /**
    	 * Constructor
    	 *
    	 */
    	function __construct(){}
        
		/**
		 * Set data for template to use
		 *
		 * @access public
		 */
		public function set_data($data)
		{
		  $this->data = $data;
		}

    	/**
    	 * Render a given template
    	 *
		 * @access public
    	 */
    	public function show()
    	{	
			# Start output buffer
            ob_start();

			# Load template
			require( A_PREFIX.'pub/theme/'.APP_TPL.'.php');
            
			# Send buffered contents 
			ob_end_flush();

    	}
		
		public function show_top()
		{
		  A::h('logo', 'index');
		  logo();
		}
        
        public function show_style()
        {
            echo SUB_DIR.'pub/theme/'.APP_STYLE.'.css';
        }
        
        public function show_jquery()
        {
            echo SUB_DIR.'pub/script/jquery-1.2.6.min.js';
        }
        
        public function show_jcorner()
        {
            echo SUB_DIR.'pub/script/jquery.corner-1.92.js';
        }	
	
	    public function show_script(){}

		public function show_nav()
		{
		  A::h('nav', 'index');
		  nav();
		}

		public function show_sidebar(){}

    	public function show_content(){}

		public function escape($value)
		{
		  return htmlentities($value, ENT_QUOTES, 'UTF-8');
		}
    }
?>
