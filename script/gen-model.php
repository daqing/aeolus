#! /usr/bin/php
<?php
  
    /*
     * Add application models
     */
  
    if (3 > $argc) {
        echo "Usage: $argv[0] [MODULE] [MODEL]...\n";
      	echo "Add MODEL(s) to a MODULE.\n\n";
      	echo "Report bugs to <kinch.zhang@gmail.com>.\n";
      	exit(0);
    }
  
    define('AEOLUS_HOME', dirname(dirname(__FILE__)));

    $module = $argv[1];
    $models = array_slice($argv, 2);
  
    $gpath = AEOLUS_HOME.'/module/'.$module;

    if (file_exists( $gpath) && is_writable($gpath)) {
        foreach ($models as $m) {
       	    $mpath = $gpath.'/model/'.$m.'.php';
       	    if (!file_exists($mpath)) {
         	    if ($res = fopen($mpath, 'w')) {
         		    $content = "<?php\n\n  /* $m model in $module module */\n  ";
         			$content .= "class $m extends AeoModel\n  {\n    ";
         		    $content .= "public function foobar()\n    {\n    }\n  } \n?>";
         
         		    if (FALSE === fwrite($res, $content))
         		      echo "[ERROR] Can't write content to '$mpath'.\n";
         		} else {
         		    echo "[ERROR] Can't open file '$mpath' to write.\n";
                }
       	    }
        }
    } else {
        echo "[ERROR] Directory '$module_path' doesn't exist or doesn't allow creating files.\n";
    }

?>