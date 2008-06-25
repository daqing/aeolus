<?php
  /*
   * AGuard class
   */

  class AGuard
  {
    private static $engine = null;

	/* Purify user input */
	public function pf($input)
	{
	  if (null === self::$engine) {
	    A::ld('guard/HTMLPurifier');
	    self::$engine = new HTMLPurifier();
	  }

	  return self::$engine->purify($input);
	}
  }
?>
