<?php
  /*
   * Factory class
   */
  
  class Aeo
  {
	private static $loaded = array();

	/* Load files */
    public function ld($path)
    {
	  $path .= '.php';
	  if (!in_array($path, self::$loaded)) {
        require($path);
		self::$loaded[] = $path;
	  }
	} 

	/* Get an instance of a view class */
    public function v($view, $group = 'this')
    {
	  if ('this' == $group) {
	    global $thisgrp;
		$group = $thisgrp;
	  }
      if (APP_DEBUG) 
	    clearstatcache();

      $obj = null;
      $path = A_PREFIX . "app/$group/view/$view";
      if (file_exists($path . '.php')) {
        self::ld('kernel/AeoView');
        self::ld($path);

        if (class_exists($view))
          $obj = new $view();
      }
      return $obj;
    }
    
	/* Get an instance of a model class */
    public function m($model, $group = 'this')
    {
	  if ('this' == $group) {
	    global $thisgrp;
		$group = $thisgrp;
	  }
      if (APP_DEBUG)
	    clearstatcache();

      $obj = null;
      $path = A_PREFIX . "app/$group/model/$model";
      if (file_exists($path . '.php')) {
        self::ld('kernel/AeoModel');
        self::ld($path);

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

      $path = A_PREFIX . "app/$group/helper/$helper";
      if (file_exists($path . '.php'))
        self::ld($path);
	}
  }
?>
