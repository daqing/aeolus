<?php if(! defined('APP_STARTED')){die('<h3>BAD REQUEST.</h3>');}
     
  /**
   * IndexIndexView view class in 'index' module
   *
   */

  class IndexIndexView extends AeolusView
  {
    function showSidebar()
    {
	  echo 'Aeolus Home';
    }

    function showContent()
    {
	  echo '<div class="section">';
	  echo 'Welcome to Aeolus framework';
	  echo '</div>';
    }

    function showScript()
    {
      ?>
      <?php
    }
  }
?>
