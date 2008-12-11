<?php

  /* IndexView view in index group */
  class IndexView extends AeoView
  {
    public function show_sidebar()
    {
	  echo 'Aeolus home page';
    }

    public function show_content()
    {
	  Aeo::ld('AeoGuard');
	  echo '<div class="section">';
      echo '<p>Aeolus is an open-source PHP framework ';
      echo 'designed for productive Web development.</p>';
      echo '</div>';
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
