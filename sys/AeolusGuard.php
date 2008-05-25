<?php if(! defined('APP_STARTED')){die('<h3>BAD REQUEST.</h3>');}
  /**
   * AeolusGuard class
   *
   */

  class AeolusGuard
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
	  # Use HTMLPurifier
	  if( null == self::$engine ){
	    AeolusFactory::loadOnce( 'HTMLPurifier/HTMLPurifier.standalone.php' );

	    $config = array('Cache.SerializerPath' => AEOLUS_HOME.'/tmp/htmlpurifier');
	    self::$engine = new HTMLPurifier($config);
      }

	  return self::$engine->purify($input);

	}

  }
?>
