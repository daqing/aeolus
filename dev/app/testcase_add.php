#! /usr/bin/php
<?php
  # Add an application testcase

  if( 2 > $argc ){
    echo "Usage: testcase_add.php 'module' 'testcase' \n";
  }else{
	$testcase = $argv[1];

    require '../init.php';

	$testcase_path = AEOLUS_ROOT."/tmp/testcase/$testcase.php";

	if( is_writable( AEOLUS_ROOT.'/tmp/' ) ){
	  if(! file_exists($testcase_path)){
	    if( $res = fopen($testcase_path,'w')){
		  $content = "<?php\n  /**\n   * $testcase testcase class ";
		  $content .= "\n   *\n   */\n\n  ";
		  $content .= "require_once 'SimpleTest/autorun.php';\n  ";
		  $content .= "#require_once '';\n\n  class ";
		  $content .= $testcase." extends UnitTestCase\n  {\n\n\n  }\n?>";

		  if( FALSE === fwrite($res,$content) ){
		    echo "[ERROR] Can't write content $content to file $testcase_path.\n";
		  }

		}else{
		  echo "[ERROR] Can't open file $testcase_path to write.\n";
		}

	  }else{
        echo "[ERROR] This testcase $testcase already exists in $testcase_path. \n";
	  }
	}else{
      echo "[ERROR] Directory:".AEOLUS_ROOT."/tmp/"." doesn't exist or doesn't allow writing files";
	}
  }
?>
