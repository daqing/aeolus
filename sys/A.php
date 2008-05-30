<?php if(! defined('AEOLUS_STARTED')){ die('<h3>BAD REQUEST</h3>');}
  /**
   * A class
   * 
   * @author Kinch Zhang <kinch.zhang@gmail.com>
   */
  
  class A
  {
    /**
     * Load a generic PHP file once and only once
     * 
     * @access public
     * @param string $path the absolute or relative path to the file
     * @return void
     * 
     */
    public function ld($path)
    {
      if(! isset($GLOBALS['included'][$path])){
	    # Security check
        if( substr($path,-4,4) != '.php' ){ $path .= '.php';}
        if( APP_DEBUG ){ clearstatcache();}
		
        require($path);
        $GLOBALS['included'][$path] = true;
	  }
	}
    
    /**
     * Load a helper function
     * 
     * @access public
     * @param string $helper the helper function name
     * @param string $module the module name
     * @return boolean true|false true if the helper exists
     * 
     */
    public function loadHelper($helper, $module='this')
    {
	  if( 'this' == $module ){
	    global $thisModule;
		$module = $thisModule;
	  }

	  # Absolute path to the helper file
      $path = AEOLUS_HOME."/app/$module/helper/$helper.php";
	  if( APP_DEBUG ){clearstatcache();}

      if( file_exists($path)){
        self::ld($path);        
      }
    }
    
    /**
     * Get an instance of an application view class
     * 
     * @param string $view the name of the view class
     * @param object $model Model object
     * @param string $module the module name
     * @return object $obj an instance of the view class
     * 
     */
    public function mkView($view, $model=null, $module='this')
    {
	  if( 'this' == $module ){
	    global $thisModule;
		$module = $thisModule;
	  }

	  # Absolute path to the view file
      $path = AEOLUS_HOME."/app/$module/view/$view.php";

      if( APP_DEBUG ){ clearstatcache();}
      $obj = null;
	  
      if( file_exists($path)){
	    # Load related classes
        self::ld('kernel/AView.php');
        self::ld($path);

        if( class_exists($view)){
          $obj = new $view();

          if($model){
            $obj->setModel($model);  
          }
        }
      }
      
      return $obj;
    }
    
  /**
     * Get an instance of an application model class
     * 
     * @param string $model the name of the model class
     * @param string $module the module name
     * @return object $obj an instance of the model class
     * 
     */
    public function mkModel($model, $module='this')
    {
	  if( 'this' == $module){
	    global $thisModule;
		$module = $thisModule;
	  }
      
	  # Absolute path to the model file
      $path = AEOLUS_HOME."/app/$module/model/$model.php";

      if( APP_DEBUG ){clearstatcache();}
      $obj = null;

      if( file_exists($path)){
	    # Load related classes
        self::ld('kernel/AModel.php');
        self::ld($path);

        if( class_exists($model)){
          $obj = new $model();
        }
      }
      
      return $obj;
    }

	/**
	 * Display errors
	 *
	 */
	public function error($message)
	{
	  require( AEOLUS_HOME.'/pub/error/aeolus_error.html' );
	  die();
	}
    
  }
?>
