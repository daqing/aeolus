<?php if(! defined('APP_STARTED'){die('<h3>BAD REQUEST.</h3>');}
  /**
   * AeolusSecurity class
   *
   */

  class AeolusSecurity
  {
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
	  AeolusFactory::loadOnce( 'HTMLPurifier/HTMLPurifier.standalone.php' );

	  $config = array('Cache.SerializerPath' => AEOLUS_HOME.'/tmp/htmlpurifier');
	  $purifier = new HTMLPurifier($config);
	  
	  return $purifier->purify($input);

	}

	/**
	 * Escape the content to avoid XSS attack
	 *
	 * @access public
	 * @param string $content the content to be escaped
	 * @return string $escaped the escaped safe content
	 *
	 */
	public function escape($content)
	{
	  # TODO: use htmlentities() and whitelishts

	}

  }
?>
