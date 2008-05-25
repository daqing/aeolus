<?php if(! defined('APP_STARTED')){die('<h3>BAD REQUEST.</h3>');}
     
  /**
   * IndexIndexView view class in 'chat' module
   *
   */

  class IndexIndexView extends AeolusView
  {
    public function showSidebar()
    {
	  echo '微言sidebar';
    }

    public function showContent()
    {
	  echo '<div class="section">';
	  echo '全部微言';
	  echo '</div>';
    }

    public function showScript()
    {
      ?>
      <?php
    }
  }
?>
