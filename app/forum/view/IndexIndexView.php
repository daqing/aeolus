<?php if(! defined('APP_STARTED')){die('<h3>BAD REQUEST.</h3>');}
     
  /**
   * IndexIndexView view class in 'forum' module
   *
   */

  class IndexIndexView extends AeolusView
  {
    public function showSidebar()
    {
	  echo 'forum sidebar';
    }

    public function showContent()
    {
	  echo '<div class="section">';
	  echo 'forum content';
	  echo '</div>';
    }

    public function showScript()
    {
      ?>
      <?php
    }
  }
?>
