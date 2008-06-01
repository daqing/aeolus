<?php
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
	 */
	private static $engine = null;

    /**
	 * Purify the user input
	 *
	 * @access public
	 * @param string $input the user input
	 * @return string $purified the purified string
	 */
	public function pf($input)
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
	    # Load files
		A::ld('guard/HTMLPurifier/Bootstrap.php');
	    A::ld('guard/HTMLPurifier.autoload.php');

		self::$engine = new HTMLPurifier();
	  }

	  return self::$engine;
	}
  }
?>
