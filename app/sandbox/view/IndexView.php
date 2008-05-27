<?php if(! defined('AEOLUS_STARTED')){ die('<h3>BAD REQUEST</h3>');}
  /**
   * IndexView view class in 'sandbox' module
   *
   */

  class IndexView extends AeolusView
  {
    public function showSidebar()
    {
	  echo 'sandbox';
    }

    public function showContent()
    {
	  echo 'Welcome to sandbox';
    }

    public function showScript()
    {
      ?>
      <?php
    }
  }
?>
