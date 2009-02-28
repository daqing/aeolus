<?php

/*
 * Factory class
 */
    
class Aeolus
{
    private static $loaded = array();
  
  	/**
     * Load a class
     *
     * This method loads a class and makes sure to load it only once
     *
     * @param string $path path to file to be included
     * @return void
     */
    public function loadClass($className)
    {
  	    $path = $className . '.php';
  	    if (!in_array($path, self::$loaded)) {
            require $path;

  		    self::$loaded[] = $path;
  	    }
  	} 
  
  	/**
     * Get an instance of a view class
     *
     */
    public function newView($view, $group='this')
    {
  	  if ('this' == $group) {
  	      global $thisgrp;

  		  $group = $thisgrp;
  	  }
      
      if (APP_DEBUG) 
  	      clearstatcache();
  
      $obj = null;
      $path = A_PREFIX . "module/$group/view/$view";
      if (file_exists($path . '.php')) {
          self::loadClass('kernel/AeoView');
          self::loadClass($path);
  
          if (class_exists($view))
            $obj = new $view();
      }

      return $obj;
    }
      
  	/* Get an instance of a model class */
    public function newModel($model, $group = 'this')
    {
  	  if ('this' == $group) {
  	    global $thisgrp;
  		$group = $thisgrp;
  	  }
        if (APP_DEBUG)
  	    clearstatcache();
  
        $obj = null;
        $path = A_PREFIX . "module/$group/model/$model";
        if (file_exists($path . '.php')) {
          self::loadClass('kernel/AeoModel');
          self::loadClass($path);
  
          if (class_exists($model)) {
            $obj = new $model();
          }
        }
        return $obj;
    }
  
  	/* Load app helper */
  	public function h($helper, $group = 'this')
  	{
        if ('this' == $group) {
  	        global $thisgrp;

  		    $group = $thisgrp;
  	    }
  
        $path = A_PREFIX . "module/$group/helper/$helper";
        if (file_exists($path . '.php'))
          self::loadClass($path);
  	}
}

?>
