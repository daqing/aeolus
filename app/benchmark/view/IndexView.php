<?php if(! defined('APP_STARTED')){die('<h3>BAD REQUEST.</h3>');}
     
  /**
   * IndexIndexView view class in 'benchmark' module
   *
   */

  class IndexView extends AeolusView
  {
    public function showSidebar()
    {
	  echo 'Benchmark Home';
    }

    public function showContent()
    {
	  echo '<div class="section">';
	  echo 'Welcome to the benchmark page';
	  echo '</div>';
    }

    public function showScript()
    {
      ?>
      <?php
    }
  }
?>
