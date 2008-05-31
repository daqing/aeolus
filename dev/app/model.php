#! /usr/bin/php
<?php
  # Add an application model

  if( 3 > $argc ){
    echo "Usage: model.php 'group' 'model' \n";
  }else{
    $group = $argv[1];
	$model = $argv[2];

    require '../init.php';

	$group_path = AEOLUS_HOME.'/app/'.$group;
	$model_path = AEOLUS_HOME.'/app/'.$group.'/model/'.$model.'.php';
	if( file_exists( $group_path ) && is_writable($group_path) ){
	  if(! file_exists($model_path)){
	    if( $res = fopen($model_path,'w')){
		  $content = "<?php if(! defined('AEOLUS_STARTED')){ die('<h3>BAD REQUEST</h3>');}";
		  $content .= "\n  /**\n   * $model model class in ";
		  $content .= "'$group' group\n   *\n   */\n\n  class ";
		  $content .= $model." extends AModel\n  {\n    ";
		  $content .= "public function foo()\n    {\n    }\n  } \n?>";

		  if( FALSE === fwrite($res,$content) ){
		    echo "[ERROR] Can't write content $content to file $model_path.\n";
		  }

		}else{
		  echo "[ERROR] Can't open file $model_path to write.\n";
		}

	  }else{
        echo "[ERROR] This model $model already exists in $model_path. \n";
	  }
	}else{
      echo "[ERROR] Directory $group_path doesn't exist or doesn't allow writing files";
	}
  }
?>
