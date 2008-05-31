<?php if(! defined('AEOLUS_STARTED')){ die('<h3>BAD REQUEST</h3>');}
  /**
   * IndexView view class in 'index' group
   *
   */

  class IndexView extends AView
  {
    public function showSidebar()
    {
    }

    public function showContent()
    {
	  echo 'Welcome to Aeolus Framework.';
    }

    public function showScript()
    {
      ?>
      <?php
    }
  }
?>
