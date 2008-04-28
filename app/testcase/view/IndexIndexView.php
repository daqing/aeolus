<?php
  /**
   * IndexIndexView view class in 'testcase' module
   *
   */

  class IndexIndexView extends AeolusView
  {
    function render_spotlight()
    {
	  echo 'Simple Test';
    }

    function render_sidebar()
    {
	  echo '<ul>';
	  echo '<li><a href="kernel">Kernel Test</a></li>';
	  echo '</ul>';
	  
    }

    function render_content()
    {
	  $this->data->run(new HtmlReporter());
    }

    function render_js()
    {
    }
  }
?>
