<?php if(! defined('AEOLUS_STARTED')){ die('<h3>BAD REQUEST</h3>');}
  /**
   * cache controller in 'index' module
   *
   */

  function cache()
  {
    # Load cache class
	A::ld( 'ACache');
	$time = date('H:i:s');
	if(! $data = ACache::fetch('data')){
	  # Cache miss
	  $data = '<h3>Cache data at: '.$time;
	  $data .= ' and will last for 1 min</h3>';
	  ACache::store($data, 'data', 60);
	}
	echo '<h3>Now is: '.$time.'</h3>';
	echo $data;

	if(! $test = ACache::fetch('test')){
	  # cache miss
	  $test = '<h3>Cached test at: '.$time.' and will last for 2 mins</h3>';
      ACache::store($test, 'test', 120);
	}

	echo $test;
  }
?>
