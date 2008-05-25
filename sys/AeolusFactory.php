<?php if(! defined('APP_STARTED')){die('<h3>BAD REQUEST</h3>');}
  /**
   * AeolusFactory class
   * 
   * @author Kinch Zhang <kinch.zhang@gmail.com>
   * 
   */
  
  class AeolusFactory
  {
    /**
     * Load a generic PHP file once and only once
     * 
     * @access public
     * @param string $path the absolute or relative path to the file
     * @return void
     * 
     */
    public function loadFile($path)
    {
      if(! isset($GLOBALS['included'][$path])){
        if( substr($path,-4,4) == '.php' ){
          # This is a PHP file so it's safe to include it
          require($path);
          $GLOBALS['included'][$path] = true;
        }else{
          # This is not a PHP file
          if( APP_DEBUG ){
            echo("<h3>[FATAL ERROR] THE FILE: '$path' is not a PHP file.</h3>");
          }else{
            die('<h3>BAD REQUEST.</h3>');
          }
        }        
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
        self::loadFile($path);        
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
    public function makeView($view, $model=null, $module='this')
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
        self::loadFile('kernel/AeolusView.php');
        self::loadFile($path);

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
    public function makeModel($model, $module='this')
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
        self::loadFile('kernel/AeolusModel.php');
        self::loadFile($path);

        if( class_exists($model)){
          $obj = new $model();
        }
      }
      
      return $obj;
    }
    
  }
?>
