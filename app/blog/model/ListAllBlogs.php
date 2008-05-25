<?php if(! defined('APP_STARTED')){die('<h3>BAD REQUEST.</h3>');}
  /**
   * ListAllBlogs model class in 'blog' module
   *
   */

  class ListAllBlogs extends AeolusModel
  {
    public function getAllBlogs()
    {
	  return 'blogs';
    }
  } 
?>
