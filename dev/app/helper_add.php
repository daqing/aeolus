#! /usr/bin/php
<?php
  # Add an application helper

  if( 3 > $argc ){
    echo "Usage: helper_add.php 'module' 'helper' \n";
	die();
  }else{
    $module = $argv[1];
	$helper = $argv[2];

	require '../init.php';

	$module_path = AEOLUS_HOME.'/app/'.$module;
	$helper_path = AEOLUS_HOME.'/app/'.$module.'/helper/'.$helper.'.php';
	if( file_exists( $module_path ) && is_writable($module_path) ){
	  if(! file_exists($helper_path)){
	    if( $res = fopen($helper_path,'w')){
		  $content = "<?php if(! defined('APP_STARTED')){die('<h3>BAD REQUEST.</h3>');}";
		  $content .= "\n  /**\n   * ".ucfirst($helper)." helper function in ";
		  $content .= "'$module' module\n   *\n   */\n\n  function ";
		  $content .= $helper."()\n  {\n    ?>\n    <div>Change Me!</div>";
		  $content .= "\n    <?php\n  }\n?>";

		  if( FALSE === fwrite($res,$content) ){
		    echo "[ERROR] Can't write content $content to file: $helper_path.\n";
	      }
		}else{
		  echo "[ERROR] Can't open file $helper_path to write.\n";
		}

	  }else{
        echo "[ERROR] This helper $helper already exists in $helper_path. \n";
	  }
	}else{
      echo "[ERROR] Directory $module_path doesn't exist or doesn't allow writing files";
	}
  }
?>
