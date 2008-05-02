#! /usr/bin/php
<?php
  /**
   * Add a module
   *
   */

  require '../init.php'; 

  if( 2 > $argc ){
    echo "[Usage] module.php modules \n";
  }else{
    for($i=1;$i<$argc;$i++){
	  clearstatcache();
	  $path = AEOLUS_HOME."/app/$argv[$i]";
	  $index = AEOLUS_HOME."/app/index.php";
	  if(! file_exists( $path )){
	    @mkdir( $path );
		@mkdir( $path.'/controller' );
		@mkdir( $path.'/model' );
		@mkdir( $path.'/view' );
		@mkdir( $path.'/helper' );
		@copy($index,$path.'/index.php');
	  }else{
	    foreach( array('controller','view','model','helper') as $v ){
		  if(! file_exists( $path."/$v" )){
		    @mkdir( $path."/$v" );
		  }
	    }
      }
	}
  }

?>
