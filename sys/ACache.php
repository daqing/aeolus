<?php
  /**
   * ACache class for caching
   *
   * @author Kinch Zhang <kinch.zhang@gmail.com>
   */

  class ACache
  {
    /**
	 * Cache backend
	 *
	 * @access private
	 */
	private static $backend = null;

	/**
	 * Set cache backend
	 * 
	 * @access private
	 */
	private function set_backend()
	{
	  # Load backend according to the configuration
	  require( A_PREFIX.'/etc/cache/backend.php' );
	  $backend = 'A'.$backend.'Cache';
	  A::ld( "cache/$backend" );

      self::$backend = new $backend();
	}

	/**
	 * Fetch data from cache
	 *
	 * @access public
	 * @param $id 
	 */
	public function fetch($id)
	{
	  if( null == self::$backend ){
	    self::set_backend();
	  }

	  return self::$backend->fetch($id);
	}

	/**
	 * Store data into cache
	 *
	 * @access public
	 * @param $data
	 * @param $id
	 */
	public function store($data, $id, $lifetime = 300)
	{
	  self::$backend->store($data,$id, $lifetime);
	}
  }
?>
