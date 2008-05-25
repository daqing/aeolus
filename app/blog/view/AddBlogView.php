<?php if(! defined('APP_STARTED')){die('<h3>BAD REQUEST.</h3>');}
     
  /**
   * AddBlogView view class in 'blog' module
   *
   */

  class AddBlogView extends AeolusView
  {
    public function showSidebar()
    {
	  AeolusFactory::loadHelper('blogSidebar');
	  blogSidebar();
    }

    public function showContent()
    {
	  AeolusFactory::loadHelper('showAddBlogForm');
	  showAddBlogForm();
    }

    public function showScript()
    {
      ?>
      <?php
    }
  }
?>
