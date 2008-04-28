<?php
  /**
   * Useful factory functions
   *
   */

  # Get an instance of an application view class
  function a_view_factory($module,$view,$data=null)
  {
    $path = AEOLUS_ROOT."/app/$module/view/$view.php";
	if( file_exists($path) ){
	  a_load( AEOLUS_ROOT.'/kernel/core/AeolusView.php' );
	  a_load($path);
	  $obj = new $view();
	  $obj->set_data($data);
	  
	  return $obj;
	}else{
      return null;
	}
  }

  # Get an instance of an application model class
  function a_model_factory($module,$model)
  {
    $path = AEOLUS_ROOT."/app/$module/model/$model.php";
	if( file_exists($path) ){
	  a_load( AEOLUS_ROOT.'/kernel/core/AeolusModel.php' );
	  a_load($path);
	  $obj = new $model();
	  
	  return $obj;
	}else{
      return null;
	}
  }

  # Get an instance of a kernel class
  function a_kernel_factory($path)
  {
	$class = basename($path,'.php');

	# Load the class
	a_load($path);

	if( class_exists($class) ){
	  return new $class();
	}else{
      return null;
	}  
  }
?>
