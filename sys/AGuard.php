<?php
  /**
   * AGuard class
   *
   * @author Kinch Zhang <kinch.zhang@gmail.com>
   */

  class AGuard
  {
    # Purifier engine
    private static $engine = null;

    /**
	 * Purify The user input
	 *
	 * @access public
	 * @param string $input The user input
	 * @return string $purified The purified string
	 */
	public function pf($input)
	{
	  if (null == self::$engine) {
	    # Get HTMLPurifier engine
	    A::ld('guard/Guard');
	    self::$engine = new HTMLPurifier();
	  }

	  return self::$engine->purify($input);
	}
  }
?>
