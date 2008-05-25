<?php if(! defined('APP_STARTED')){die('<h3>BAD REQUEST.</h3>');}
     
  /**
   * LoginView view class in 'index' module
   *
   */

  class LoginView extends AeolusView
  {
    public function showSidebar()
    {
	  echo '登录';
    }

    public function showContent()
    {
	  echo '<div class="section">';
	  echo '登录';
	  echo '</div>';
    }

    public function showScript()
    {
      ?>
      <?php
    }
  }
?>
