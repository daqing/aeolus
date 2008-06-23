<?php

  /* IndexView view in index group */
  class IndexView extends AView
  {
    public function show_sidebar()
    {
	  echo 'Aeolus home';
    }

    public function show_content()
    {
	  A::ld('AGuard');
	  echo '<div class="section">';
	  echo AGuard::pf($this->data);
	  echo '</div>';
    }

    public function show_script()
    {
      ?>
      <?php
    }
  }
?>
