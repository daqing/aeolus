<?php

  /* IndexView view in index group */
  class IndexView extends AeoView
  {
    public function show_sidebar()
    {
	  echo 'Aeolus home';
    }

    public function show_content()
    {
	  Aeo::ld('AeoGuard');
	  echo '<div class="section">';
	  echo AeoGuard::pf($this->data);
	  echo '</div>';
    }

    public function show_script()
    {
      ?>
      <?php
    }
  }
?>
