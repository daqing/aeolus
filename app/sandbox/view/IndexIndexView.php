<?php
  /**
   * IndexIndexView class in 'sandbox' module
   *
   */

  class IndexIndexView extends AeolusView
  {
    function render_spotlight()
    {
	  echo 'Welcome to sandbox';
    }

    function render_sidebar()
    {
	  echo '<ul>';
	  echo '<li><a href="'.APP_BASEURL.'/">Home</a></li>';
	  echo '<li>Sandbox</li>';
	  echo '</ul>';
    }

    function render_content()
    {
	  echo '<p>Sandbox is where you can play with Aeolus for fun. ';
    }

    function render_js()
    {
    }
  }
?>
