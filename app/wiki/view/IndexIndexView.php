<?php if(! defined('APP_STARTED')){die('<h3>BAD REQUEST.</h3>');}
     
  /**
   * IndexIndexView view class in 'wiki' module
   *
   */

  class IndexIndexView extends AeolusView
  {
    public function showSidebar()
    {
	  echo 'wiki sidebar';
    }

    public function showContent()
    {
	  echo '<div class="section">';
	  echo 'wiki content';
	  echo '</div>';
    }

    public function showScript()
    {
      ?>
      <?php
    }
  }
?>
