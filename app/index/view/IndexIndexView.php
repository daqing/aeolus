<?php if(! defined('APP_STARTED')){die('<h3>BAD REQUEST.</h3>');}
     
  /**
   * IndexIndexView view class in 'index' module
   *
   */

  class IndexIndexView extends AeolusView
  {
    function showNavigator()
    {
	  echo 'nav';
    }

    function showSidebar()
    {
	  global $thisModule;
	  echo 'sidebar<br/>';

    }

    function showContent()
    {
	  echo 'content';
    }

    function showScript()
    {
      ?>
      <?php
    }
  }
?>
