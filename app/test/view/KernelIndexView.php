<?php
  /**
   * KernelIndexView view class in 'testcase' module
   *
   */

  class KernelIndexView extends AeolusView
  {
    function render_spotlight()
    {
	  echo 'Kernel Test';
    }

    function render_sidebar()
    {
	  echo 'Kernel Test';
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
