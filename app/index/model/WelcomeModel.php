<?php

  /* WelcomeModel model in index group */
  class WelcomeModel extends AeoModel
  {
    public function get_msg()
    {
	  return '<p>Message cached  at '. date('d-m-Y G:i:s') . ' for 5 minutes.';
    }
  } 
?>
