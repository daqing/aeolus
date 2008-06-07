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
	 * Purify the user input
	 *
	 * @access public
	 * @param string $input the user input
	 * @return string $purified the purified string
	 */
	public function pf($input)
	{
	  if( null == self::$engine ){
	    # Get HTMLPurifier engine
	    A::ld('guard/HTMLPurifier');
	    self::$engine = new HTMLPurifier();
	  }

	  return self::$engine->purify($input);

	}
  }
?>
