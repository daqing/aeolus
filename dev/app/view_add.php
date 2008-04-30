#! /usr/bin/php
<?php
  # Add an application view

  if( 3 > $argc ){
    echo "Usage: view_add.php 'module' 'view' \n";
  }else{
    $module = $argv[1];
	$view = $argv[2];

    require '../init.php';
	 
	$module_path = AEOLUS_ROOT.'/app/'.$module;
	$view_path = AEOLUS_ROOT.'/app/'.$module.'/view/'.$view.'.php';
	if( file_exists( $module_path ) && is_writable($module_path) ){
	  if(! file_exists($view_path)){
	    if( $res = fopen($view_path,'w')){
		  $content = "<?php\n  /**\n   * $view view class in ";
		  $content .= "'$module' module\n   *\n   */\n\n  class ";
		  $content .= $view." extends AeolusView\n  {";
		  $content .= "\n    function showNavigator()\n    {\n    }\n";
		  $content .= "\n    function showSidebar()\n    {\n    }\n";
		  $content .= "\n    function showNotice()\n    {\n    }\n";
		  $content .= "\n    function showContent()\n    {\n    }\n";
		  $content .= "\n    functio showScript()\n    {\n    }\n";
		  $content .= "  }\n?>";

		  if( FALSE === fwrite($res,$content) ){
		    echo "[ERROR] Can't write content $content to file $view_path.\n";
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
