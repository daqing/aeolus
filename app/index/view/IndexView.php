<?php if(! defined('AEOLUS_STARTED')){ die('<h3>BAD REQUEST</h3>');}
  /**
   * IndexView view class in 'index' module
   *
   */

  class IndexView extends AeolusView
  {
    public function showSidebar()
    {
	  echo 'Index';
    }

    public function showContent()
    {
	  echo 'Welcome to Aeolus Framework';
    }

    public function showScript()
    {
      ?>
      <?php
    }
  }
?>
