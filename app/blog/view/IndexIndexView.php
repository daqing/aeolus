<?php if(! defined('APP_STARTED')){die('<h3>BAD REQUEST.</h3>');}
     
  /**
   * IndexIndexView view class in 'blog' module
   *
   */

  class IndexIndexView extends AeolusView
  {
    public function showSidebar()
    {
	  AeolusFactory::loadHelper('blogSidebar');
	  blogSidebar();
    }

    public function showContent()
    {
	  $blogs = $this->model->getAllBlogs();
	  echo '<div class="section">';
      $this->escape(var_dump($blogs));
	  echo '</div>';

	  echo '<div class="section">';
      $this->escape(var_dump($blogs));
	  echo '</div>';
    }

    public function showScript()
    {
      ?>
	  $(function(){
	  });
      <?php
    }
  }
?>
