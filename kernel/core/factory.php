<?php
  /**
   * Factory method
   *
   */

  function app_view_factory($module,$view,$data=null)
  {
    $path = AEOLUS_ROOT."/app/$module/view/$view.php";
	if( file_exists($path) ){
	  aeolus_load( AEOLUS_ROOT.'/kernel/core/View.php' );
	  aeolus_load($path);
	  $obj = new $view($data);
	  
	  return $obj;
	}else{
      return null;
	}
  }

  function app_model_factory($module,$model)
  {
    $path = AEOLUS_ROOT."/app/$module/model/$model.php";
	if( file_exists($path) ){
	  aeolus_load( AEOLUS_ROOT.'/kernel/core/Model.php' );
	  aeolus_load($path);
	  $obj = new $model();
	  
	  return $obj;
	}else{
      return null;
	}
  }

?>
