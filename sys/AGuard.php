<?php if(! defined('AEOLUS_STARTED')){ die('<h3>BAD REQUEST</h3>');}
  /**
   * AGuard class
   *
   * @author Kinch Zhang <kinch.zhang@gmail.com>
   */

  class AGuard
  {
    /**
	 * Purifier Engine
	 *
	 * Default engine is HTMLPurifier
	 *
	 */
	private static $engine = null;

    /**
	 * Purify the user input
	 *
	 * @access public
	 * @param string $input the user input
	 * @return string $purified the purified string
	 *
	 */
	public function purify($input)
	{
	  # Get engine
	  $engine = self::getEngine();

	  return $engine->purify($input);

	}

	/**
	 * Set HTMLPurifier engine
	 *
	 * @access private
	 */
	private function getEngine()
	{
	  if( null == self::$engine){
	    A::ld('HTMLPurifier/HTMLPurifier.standalone.php');

		# Config
		$config = array(
		  'Cache.SerializerPath' => AEOLUS_HOME.'/tmp/htmlpurifier',
		);

		self::$engine = new HTMLPurifier($config);
	  }

	  return self::$engine;
	}
  }
?>
