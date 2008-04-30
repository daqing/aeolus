<?php
  /**
   * IndexIndexView view class in 'index' module
   *
   */

  class IndexIndexView extends AeolusView
  {
    function showNavigator()
    {
	  echo 'Navigator';
    }

    function showSidebar()
    {
	  echo 'sidebar';
    }

    function showNotice()
    {
	  echo 'Notice';
    }

    function showContent()
    {
	  echo 'content';
    }

    function showScript()
    {
	  ?>
	  $(function(){});
	  <?php
    }
  }
?>
