#! /usr/bin/php
<?php
  # Add an application view

  if( 3 > $argc ){
    echo "Usage: view.php 'group' 'view' \n";
  }else{
    $group = $argv[1];
	$view = $argv[2];

    require '../init.php';
	 
	$group_path = AEOLUS_HOME.'/app/'.$group;
	$view_path = AEOLUS_HOME.'/app/'.$group.'/view/'.$view.'.php';
	if( file_exists( $group_path ) && is_writable($group_path) ){
	  if(! file_exists($view_path)){
	    if( $res = fopen($view_path,'w')){
		  $content = "<?php if(! defined('AEOLUS_STARTED')){ die('<h3>BAD REQUEST</h3>');}";
		  $content .= "\n  /**\n   * $view view class in ";
		  $content .= "'$group' group\n   *\n   */\n\n  class ";
		  $content .= $view." extends AView\n  {";
		  $content .= "\n    public function showSidebar()\n    {\n    }\n";
		  $content .= "\n    public function showContent()\n    {\n    }\n";
		  $content .= "\n    public function showScript()\n    {";
		  $content .= "\n      ?>\n      <?php\n    }\n";
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
      echo "[ERROR] The directory $group_path doesn't exist or doesn't allow writing files";
	}
  }
?>
