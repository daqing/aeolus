#! /usr/bin/php
<?php
  # Add an application view

  if( 3 > $argc ){
    echo "Usage: view_add.php 'module' 'view' \n";
	die();
  }else{
    $module = $argv[1];
	$view = $argv[2];

	define('AEOLUS_ROOT',trim(dirname(dirname(__FILE__))));
    
	$module_path = AEOLUS_ROOT.'/app/'.$module;
	$view_path = AEOLUS_ROOT.'/app/'.$module.'/view/'.$view.'.php';
	if( file_exists( $module_path ) && is_writable($module_path) ){
	  if(! file_exists($view_path)){
	    if( $res = fopen($view_path,'w')){
		  $content = "<?php\n  /**\n   * ".ucfirst($view)." class in ";
		  $content .= "'$module' module\n   *\n   */\n\n  class ";
		  $content .= $view." extends AeolusView\n  {\n    function render_spotlight()\n    {\n    }\n";
		  $content .= "\n    function render_sidebar()\n    {\n    }\n";
		  $content .= "\n    function render_content()\n    {\n    }\n\n    function ";
		  $content .= "render_js()\n    {\n    }\n  }\n?>";

		  if( FALSE === fwrite($res,$content) ){
		    echo "[ERROR] Can't write content $content to file $view_path.\n";
	      }else{
            echo "[INFO] File: $view_path has been successfully created ! \n";
		  }

		}else{
		  echo "[ERROR] Can't open file $view_path to write.\n";
		}

	  }else{
        echo "[ERROR] This view $view already exists as $view_path. \n";
	  }
	}else{
      echo "[ERROR] The directory $module_path doesn't exist or doesn't allow writing files";
	}
  }
?>
