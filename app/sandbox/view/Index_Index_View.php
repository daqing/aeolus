<?php
  /**
   * Index_Index_View class in 'sandbox' module
   *
   */

  class Index_Index_View extends View
  {
    function get_spotlight()
    {
	  echo 'Sandbox';
    }

    function get_control()
    {
	  echo 'This is a sandbox control panel';
    }

    function get_sections()
    {
	  ?><div class="section"><h3>Welcome to Aeolus sandbox</h3></div>
	  <?php
    }

    function get_javascript()
    {
    }
  }
?>
