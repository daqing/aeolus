<?php

  # 'IndexView' view in 'index' group
  class IndexView extends AView
  {
    public function showSidebar()
    {
	  A::h('sidebar');
	  sidebar();
    }

    public function showContent()
    {
	  A::ld('AGuard.php');
	  echo '<div class="section">';
	  echo AGuard::pf($this->data);
	  echo '</div>';
    }

    public function showScript()
    {
      ?>
      <?php
    }
  }
?>
