<?php if(! defined('APP_STARTED')){ die('<h3>BAD REQUEST.</h3>');}
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
		  # TODO: the output content should be escaped
		  #       by HTMLPurifier to avoid XSS attace.
            ob_start();

			require( AEOLUS_HOME.'/pub/theme/'.APP_THEME.'.php');

			ob_end_flush();

    	}

		public function showHeader()
		{
		   echo '<div id="message">';
		   echo '</div>';
		   echo '<a href="'.APP_BASE.'"><img src="'.APP_SUB.'/pub/image/aeolus.gif"';
		   echo ' alt="Aeolus" /></a>';
		  
		}
        
        public function showTheme()
        {
            echo APP_SUB.'/pub/theme/'.APP_THEME.'.css';
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
		  echo '<a href="'.APP_BASE.'">首页</a>';
		  echo '<a href="'.APP_BASE.'/forum">论坛</a>';
		  echo '<a href="'.APP_BASE.'/blog">博客</a>';
		  echo '<a href="'.APP_BASE.'/wiki">Wiki</a>';
		  echo '<a href="'.APP_BASE.'/chat">微言</a>';
		}

		public function showsidebar(){}

		public function showNotice(){}

    	public function showContent(){}

		public function escape($value)
		{
		  return htmlentities($value, ENT_QUOTES, 'UTF-8');
		}
    }
?>
