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
      ob_start();
  	  require( A_PREFIX.'pub/theme/'.APP_TPL.'.php');
      ob_end_flush();
    }
  	
  	/**
  	 * Render the style sheet
  	 *
  	 * @access public
  	 */
    public function show_style()
    {
      echo SUB_DIR.'pub/theme/'.APP_STYLE.'.css';
    }
      
  	/**
  	 * Render jQuery
  	 *
  	 * @access public
  	 */
    public function show_jquery()
    {
      echo SUB_DIR.'pub/script/jquery-1.2.6.min.js';
    }
      
  	/**
  	 * Render jCorner plugin
  	 *
  	 * @access public
  	 */
    public function show_jcorner()
    {
        echo SUB_DIR.'pub/script/jquery.corner-1.92.js';
    }  

  	/**
  	 * Render scripts in child view classes
  	 *
  	 * @access public 
  	 */
    public function show_script(){}

  	/**
  	 * Use a helper to show top area
  	 *
  	 * @access public
  	 */
  	public function show_top()
  	{
  	  A::h('top', 'index');
  	  top();
  	}
  
	/**
	 * Use a helper to show navigation menu
	 *
	 * @access public
	 */
  	public function show_nav()
  	{
  	  A::h('nav', 'index');
  	  nav();
  	}

	/**
	 * Show sidebar in child view classes
	 *
	 * @access public
	 */
  	public function show_sidebar(){}

	/**
	 * Show content in child view classes
	 *
	 * @access public
	 */
    public function show_content(){}

	/**
	 * Escape data to make it HTML-safe
	 *
	 * @access public
	 * @param string $data Data to be escaped
	 * @return string $result Return the escaped data
	 */
  	public function escape($data)
  	{
  	  return htmlentities($data, ENT_QUOTES, 'UTF-8');
  	}
  }
?>
