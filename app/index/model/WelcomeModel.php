<?php

  /* WelcomeModel model in index group */
  class WelcomeModel extends AeoModel
  {
    public function get_msg()
    {
	  return 'hello, world @ '. date('d-m-Y G:i:s');
    }
  } 
?>
