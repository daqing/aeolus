<?php
  /**
   * Index_Index_View class in 'sandbox' module
   *
   */

  class Index_Index_View extends View
  {
    function render_spotlight()
    {
	  echo 'Sandbox';
    }

    function render_control()
    {
	  echo 'This is a sandbox control panel';
    }

    function render_sections()
    {
	  ?><div class="section"><h3>Welcome to Aeolus sandbox</h3></div>
	  <p>Here's debug info about Model and its driver<p>
	  <?php var_dump($this->data);var_dump($this->data->driver);
    }

    function render_js()
    {
	  app_helper_load('sandbox','sb_index_index');
	  sb_index_index();
    }

	function render_included_js()
	{
	  echo AEOLUS_SUBDIR.'/pub/js/app/sandbox.js';
	}
  }
?>
