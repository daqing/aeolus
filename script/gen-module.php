#! /usr/bin/php
<?php
  
    /*
     * Add modules
     */
  
    if (2 > $argc) {
        echo "Usage: $argv[0] [MODULE]...\n";
      	echo "Add MODULE(s).\n\n";
      	echo "Report bugs to <kinch.zhang@gmail.com>.\n";
      	exit(0);
    }
  
    define('AEOLUS_HOME', dirname(dirname(__FILE__)));
  
    clearstatcache();

    for ( $i=1; $i<$argc; $i++) {
        $path = AEOLUS_HOME."/module/$argv[$i]";
  
        if (!file_exists($path)) {
    	    @mkdir( $path );
    		@mkdir( $path.'/controller' );
    		@mkdir( $path.'/model' );
    		@mkdir( $path.'/view' );
    		@mkdir( $path.'/helper' );
    	  } else {
    	      foreach (array('controller','view','model','helper') as $v) {
    		    if (!file_exists( $path."/$v"))
    		        @mkdir( $path."/$v" );
    	      }
        }
    }

?>
