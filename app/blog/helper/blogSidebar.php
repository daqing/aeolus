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
	<ul>
	<li><a href="<?php echo $base;?>">所有文章</a></li>
	<li><a href="<?php echo $base;?>/add">写日志</a></li>
	</ul>
    <?php
  }
?>
