#! /usr/bin/php
<?php
  # Add an application model

  if( 3 > $argc ){
    echo "Usage: model_add.php 'module' 'model' \n";
  }else{
    $module = $argv[1];
	$model = $argv[2];

    require '../init.php';

	$module_path = AEOLUS_ROOT.'/app/'.$module;
	$model_path = AEOLUS_ROOT.'/app/'.$module.'/model/'.$model.'.php';
	if( file_exists( $module_path ) && is_writable($module_path) ){
	  if(! file_exists($model_path)){
	    if( $res = fopen($model_path,'w')){
		  $content = "<?php\n  /**\n   * $model model class in ";
		  $content .= "'$module' module\n   *\n   */\n\n  class ";
		  $content .= $model." extends AeolusModel\n  {\n\n\n  }\n?>";

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
      echo "[ERROR] Directory $module_path doesn't exist or doesn't allow writing files";
	}
  }
?>
