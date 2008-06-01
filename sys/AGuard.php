<?php
  /**
   * AGuard class
   *
   * @author Kinch Zhang <kinch.zhang@gmail.com>
   */

  class AGuard
  {
    /**
	 * Purify the user input
	 *
	 * @access public
	 * @param string $input the user input
	 * @return string $purified the purified string
	 */
	public function pf($input)
	{
	  # Get HTMLPurifier engine
	  A::ld('guard/HTMLPurifier.php');
	  $engine = new HTMLPurifier();

	  return $engine->purify($input);

	}
  }
?>
