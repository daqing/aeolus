<?php
  /**
   * IndexIndexView class in 'index' module
   *
   */

  class IndexIndexView extends AeolusView
  {
    function render_spotlight()
    {
	  echo 'Welcome to Aeolus';
    }

    function render_sidebar()
    {
	  echo '<ul>';
	  echo '<li>Home</li>';
	  echo '<li><a href="'.APP_BASEURL.'/demo/">demo</a></li>';
	  echo '<li><a href="'.APP_BASEURL.'/testcase/">test</a></li>';
	  echo '</ul>';
	}


    function render_content()
    {
	  echo '<p>Aeolus is an open-source PHP Web framework ';
	  echo 'that\'s fast,lightweight and flexible.</p>';
	  echo '<p>See <a href="http://code.google.com/p/aeolus/">our ';
	  echo 'homepage</a> for more details.</p>';
    }

    function render_js()
    {
    }
  }
?>
