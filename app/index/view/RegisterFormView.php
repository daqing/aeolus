<?php if(! defined('APP_STARTED')){die('<h3>BAD REQUEST.</h3>');}
     
  /**
   * RegisterFormView view class in 'index' module
   *
   */

  class RegisterFormView extends AeolusView
  {
    public function showSidebar()
    {
	  echo '注册';
    }

    public function showContent()
    {
	  echo '<div class="section">';
	  echo '注册';
	  echo '</div>';
    }

    public function showScript()
    {
      ?>
      <?php
    }
  }
?>
