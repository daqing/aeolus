<?php
  /**
   * AeolusFactory class
   * 
   * @author Kinch Zhang <kinch.zhang@gmail.com>
   * 
   */
  
  class AeolusFactory
  {
    /**
     * Load a generic PHP file once
     * 
     * @access public
     * @param string $path the absolute or relative path to the file
     * @return void
     * 
     */
    public function loadOnce($path)
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
            die('<h3>[ERROR 400] BAD REQUEST.</h3>');
          }
        }        
      }
    }
    
    /**
     * Load a helper function
     * 
     * @access public
     * @param string $module the module name
     * @param string $helper the helper function name
     * @return boolean true|false true if the helper exists
     * 
     */
    public function loadHelper($module,$helper)
    {
      $path = AEOLUS_HOME."/app/$module/helper/$helper.php";
	  if( APP_DEBUG ){clearstatcache();}
      if( file_exists($path)){
        self::loadOnce($path);        
      }
      
      return function_exists($helper);
    }
    
    /**
     * Get an instance of an application view class
     * 
     * @param string $module the module name
     * @param string $view the name of the view class
     * @param mixed $data the data to be passed to the view class(optional)
     * @return object $obj an instance of the view class
     * 
     */
    public function makeView($module, $view, $data=null)
    {
      $path = AEOLUS_HOME."/app/$module/view/$view.php";
      if( APP_DEBUG ){clearstatcache();}

      $obj = null;
	  
      if( file_exists($path)){
        self::loadOnce('kernel/AeolusView.php');
        self::loadOnce($path);
        if( class_exists($view)){
          $obj = new $view();
          if(! is_null($data)){
            $obj->setData($data);  
          }
        }
      }
      
      return $obj;
    }
    
  /**
     * Get an instance of an application model class
     * 
     * @param string $module the module name
     * @param string $model the name of the model class
     * @return object $obj an instance of the model class
     * 
     */
    public function makeModel($module, $model)
    {
      $path = AEOLUS_HOME."/app/$module/model/$model.php";
      if( APP_DEBUG ){clearstatcache();}

      $obj = null;

      if( file_exists($path)){
        self::loadOnce('AeolusModel.php');
        self::loadOnce($path);
        if( class_exists($model)){
          $obj = new $model();
        }
      }
      
      return $obj;
    }
    
  }
?>
