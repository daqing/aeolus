<?php

  # 'IndexView' view in 'index' group
  class IndexView extends AView
  {
    public function showSidebar()
    {
	  $this->getHelper('sidebar');
	  sidebar();
    }

    public function showContent()
    {
	  echo '<div class="section">';
	  echo $this->data;
	  echo '</div>';
    }

    public function showScript()
    {
      ?>
      <?php
    }
  }
?>
