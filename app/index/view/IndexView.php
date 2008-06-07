<?php

  # 'IndexView' view in 'index' group
  class IndexView extends AView
  {
    public function show_sidebar()
    {
	  A::h('sidebar','index');
	  sidebar();
    }

    public function show_content()
    {
	  echo '<div class="section">';
	  echo '<p>Hello, world</p>';
	  echo '</div>';
    }

    public function show_script()
    {
      ?>
      <?php
    }
  }
?>
