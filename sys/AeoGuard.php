<?php
  /*
   * Security
   */

  class AeoGuard
  {
    private static $engine = null;

	/* Purify user input */
	public function pf($input)
	{
	  if (null === self::$engine) {
	    Aeolus::loadClass('guard/HTMLPurifier');
	    self::$engine = new HTMLPurifier();
	  }

	  return self::$engine->purify($input);
	}
  }
?>
