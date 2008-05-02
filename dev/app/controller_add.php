#! /usr/bin/php
<?php
  # Add an application controller

  if( 3 > $argc ){
    echo "Usage: controller_add.php 'module' 'controller' \n";
	die();
  }else{
    $module = $argv[1];
	$controller = $argv[2];
    
	require '../init.php';

	$module_path = AEOLUS_HOME.'/app/'.$module;
	$controller_path = AEOLUS_HOME.'/app/'.$module.'/controller/'.$controller.'.php';
	if( file_exists( $module_path ) && is_writable($module_path) ){
	  if(! file_exists($controller_path)){
	    if( $res = fopen($controller_path,'w')){
		  $content = "<?php if(! defined('APP_STARTED')){die('<h3>BAD REQUEST.</h3>');}";
		  $content .= "\n  /**\n   * $controller controller in ";
		  $content .= "'$module' module\n   *\n   */\n\n  function ";
		  $content .= "index()\n  {\n    echo 'Hello,world! [From $controller";
		  $content .= " controller in \'$module\' module]';\n  }\n?>";

		  if( FALSE === fwrite($res,$content) ){
		    echo "[ERROR] Can't write content $content to file $controller_path.\n";
		  }

		}else{
		  echo "[ERROR] Can't open file $controller_path to write.\n";
		}

	  }else{
        echo "[ERROR] This controller $controller already exists as $controller_path. \n";
	  }
	}else{
      echo "[ERROR] The directory $module_path doesn't exist or doesn't allow writing files";
	}
  }
?>
