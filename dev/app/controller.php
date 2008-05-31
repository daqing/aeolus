#! /usr/bin/php
<?php
  # Add an application controller

  if( 3 > $argc ){
    echo "Usage: controller.php 'group' 'controller' \n";
	die();
  }else{
    $group = $argv[1];
	$controller = $argv[2];
    
	require '../init.php';

	$group_path = AEOLUS_HOME.'/app/'.$group;
	$controller_path = AEOLUS_HOME.'/app/'.$group.'/controller/'.$controller.'.php';
	if( file_exists( $group_path ) && is_writable($group_path) ){
	  if(! file_exists($controller_path)){
	    if( $res = fopen($controller_path,'w')){
		  $content = "<?php if(! defined('AEOLUS_STARTED')){ die('<h3>BAD REQUEST</h3>');}";
		  $content .= "\n  /**\n   * '$controller' controller in ";
		  $content .= "'$group' group\n   *\n   */\n\n  function ";
		  $content .= "$controller()\n  {\n    echo 'Hello,world! [From $controller";
		  $content .= " controller in \'$group\' group]';\n  }\n?>";

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
      echo "[ERROR] The directory $group_path doesn't exist or doesn't allow writing files";
	}
  }
?>
