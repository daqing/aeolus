<?php
  /**
   * IndexIndexView view class in 'demo' module
   *
   */

  class IndexIndexView extends AeolusView
  {
    function render_spotlight()
    {
	  echo 'Welcome to the demo page';
    }

    function render_sidebar()
    {
	  echo '<ul>';
	  echo '<li><a href="blog">Blog</a></li>';
	  echo '</ul>';
    }

    function render_content()
    {
	  echo '<p>Here you can find some demo apps</p>';
    }

    function render_js()
    {
    }
  }
?>
