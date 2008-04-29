<?php
  /**
   * IndexIndexView view class in 'testcase' module
   *
   */

  class IndexIndexView extends AeolusView
  {
    function render_spotlight()
    {
	  echo 'SimpleTest Home';
    }

    function render_sidebar()
    {
	  echo '<ul>';
	  echo '<li><a href="kernel">Kernel Test</a></li>';
	  echo '</ul>';
	  
    }

    function render_content()
    {
	  echo 'We\'re using SimpleTest for unit-testing';
    }

    function render_js()
    {
    }
  }
?>
