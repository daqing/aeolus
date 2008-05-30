<?php if(! defined('AEOLUS_STARTED'){ die('<h3>BAD REQUEST</h3>');}
  /**
   * ACache class for caching
   *
   * @author Kinch Zhang <kinch.zhang@gmail.com>
   */

  class ACache
  {
    /**
	 * Cache driver
	 *
	 * @access private
	 */
	private static $driver = null;

	/**
	 * Set cache driver
	 * 
	 * @access private
	 */
	private function setDriver()
	{
	  if( null == self::$driver ){
	    # Load driver according to the configuration
	    AeolusFactory::loadFile( AEOLUS_HOME.'/etc/cache.php' );
	    $driver = 'Aeolus'.CACHE_DRIVER.'Cache';
	    AeolusFactory::loadFile( "cache/$driver.php" );

	    self::$driver = new $driver();
	  }
	}

	/**
	 * Fetch data from cache
	 *
	 * @access public
	 * @param $id 
	 */
	public function fetch($id)
	{
	  self::setDriver();
	  return self::$driver->fetch($id);
	}

	/**
	 * Store data into cache
	 *
	 * @access public
	 * @param $data
	 * @param $id
	 */
	public function store($data, $id)
	{
	  self::setDriver();
	  self::$driver->store($data,$id);
	}
  }
?>
