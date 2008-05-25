<?php if(! defined('APP_STARTED')){die('<h3>BAD REQUEST.</h3>');}
  /**
   * BlogSidebar helper function in 'blog' module
   *
   */

  function blogSidebar()
  {
    global $thisModule;
	$base = APP_BASE."/$thisModule";
    ?>
	<a href="<?php echo $base;?>">所有文章</a>
	<a href="<?php echo $base;?>/add">写日志</a>
    <?php
  }
?>
