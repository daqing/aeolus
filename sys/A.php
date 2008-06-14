<?php
  /**
   * A class
   * 
   * @author Kinch Zhang <kinch.zhang@gmail.com>
   */
  
  class A
  {
    /**
	 * Loaded files
	 *
	 * @access private
	 */
	private static $loaded = array();
    /**
     * Load a generic PHP file once and only once
     * 
     * @access public
     * @param string $path the absolute or relative path to the file
     * @return void
     */
    public function ld($path)
    {
	  $path .= '.php';
	  if(! in_array($path, self::$loaded) ){
        require($path);
		self::$loaded[] = $path;
	  }
	} 

    /**
     * Get an instance of an application view class
     * 
	 * @access public
     * @param string $view the name of the view class
     * @param object $model Model object
     * @param string $group the group name
     * @return object $obj an instance of the view class
     */
    public function v($view, $data = null, $group = 'this')
    {
	  # Set group
	  if( 'this' == $group ){
	    global $thisgrp;
		$group = $thisgrp;
	  }

      if( APP_DEBUG ){ clearstatcache();}
      $obj = null;
	  
	  # Absolute path to the view file
      $path = A_PREFIX."app/$group/view/$view";
      if( file_exists($path.'.php')){
	    # Load related classes
        self::ld('kernel/AView');
        self::ld($path);

        if( class_exists($view)){
		  # Get an instance of this view class
          $obj = new $view();

          if($data){
            $obj->set_data($data);  
          }
        }
      }
      
      return $obj;
    }
    
    /**
     * Get an instance of an application model class
     * 
	 * @access public
     * @param string $model the name of the model class
     * @param string $group the group name
     * @return object $obj an instance of the model class
     */
    public function m($model, $group = 'this')
    {
	  if( 'this' == $group){
	    global $thisgrp;
		$group = $thisgrp;
	  }

      if( APP_DEBUG ){clearstatcache();}
      $obj = null;

	  # Absolute path to the model file
      $path = A_PREFIX."app/$group/model/$model";
      if( file_exists($path.'.php')){
	    # Load related classes
        self::ld('kernel/AModel');
        self::ld($path);

        if( class_exists($model)){
		  # Get an instance of this model class
          $obj = new $model();
        }
      }
      
      return $obj;
    }

	/**
	 * Load an application helper
	 *
	 * @access public
	 * @param string $helper Helper name
	 * @param string $group Group name
	 */
	public function h($helper, $group = 'this')
	{
      if( 'this' == $group ){
	    global $thisgrp;
		$group = $thisgrp;
	  }

	  # Absolute path to the helper file
      $path = A_PREFIX."app/$group/helper/$helper";
      if( file_exists($path.'.php')){
        A::ld($path);        
      }
	}

  }
?>
