<?php

  # 'DocView' view in 'index' group
  class DocView extends AView
  {
    public function showSidebar()
    {
	  echo 'Doc';
    }

    public function showContent()
    {
	  echo '<div class="section">';
	  echo 'Welcome to Aeolus doc page.';
	  echo '</div>';
    }

    public function showScript()
    {
      ?>
      <?php
    }
  }
?>
