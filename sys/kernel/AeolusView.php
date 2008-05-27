<?php if(! defined('AEOLUS_STARTED')){ die('<h3>BAD REQUEST</h3>');}
    /**
     * AeolusView class
     * 
     * @author Kinch Zhang <kinch.zhang@gmail.com>
     */

	class AeolusView
	{
    	/**
    	 * Page title
    	 *
    	 * @access public
    	 *
    	 */
    	public $title = 'Index';
		
		/**
		 * Model object
		 *
		 */
		public $model = null;

    
        /**
    	 * Constructor
    	 *
    	 */
    	function __construct(){}
        
		/**
		 * Set data for template to use
		 *
		 */
		public function setModel($model)
		{
		  $this->model = $model;
		}

    	/**
    	 * Render a given template
    	 *
    	 */
    	public function show()
    	{	
			# Start output buffer
            ob_start();

			# Load template
			require( AEOLUS_HOME.'/pub/theme/'.APP_TPL.'.php');
            
			# Send buffered contents 
			ob_end_flush();

    	}
		
		public function showTop()
		{
		   echo '<div id="message">';
		   echo '</div>';
		   echo '<a href="'.APP_PREFIX.'"><img width="165" height="34" ';
		   echo 'src="'.APP_SUB.'/pub/image/logo.gif" ';
		   echo 'alt="Logo" /></a>';
		  
		}
        
        public function showStyle()
        {
            echo APP_SUB.'/pub/theme/'.APP_STYLE.'.css';
        }
        
        public function showJquery()
        {
            echo APP_SUB.'/pub/script/jquery-1.2.3.min.js';
        }
        
        public function showJqueryCorner()
        {
            echo APP_SUB.'/pub/script/jquery.corner-1.92.js';
        }	
	
	    public function showScript(){}

		public function showNavigator()
		{
		  echo '<a href="'.APP_PREFIX.'">首页</a>&middot;';
		  echo '<a href="'.APP_PREFIX.'/sandbox/">Sandbox</a>';
		}

		public function showsidebar(){}

    	public function showContent(){}

		public function escape($value)
		{
		  return htmlentities($value, ENT_QUOTES, 'UTF-8');
		}
    }
?>
