<?php if(! defined('AEOLUS_STARTED')){ die('<h3>BAD REQUEST</h3>');}
  /**
   * IndexView view class in 'index' group
   *
   */

  class IndexView extends AView
  {
    public function showSidebar()
    {
    }

    public function showContent()
    {
	  var_dump($this->data);
    }

    public function showScript()
    {
      ?>
	  $(function(){
	    $("#sidebar").html('<span style="background-color:#EFEFEF;">Index</span>');
	  });
      <?php
    }
  }
?>
