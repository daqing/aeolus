<?php if(! defined('APP_STARTED')){die('<h3>BAD REQUEST.</h3>');}
     
  /**
   * IndexIndexView view class in 'index' module
   *
   */

  class IndexIndexView extends AeolusView
  {
    function showSidebar()
    {
	  global $thisModule;
	  echo 'sidebar<br/>';

    }

    function showContent()
    {
      $db = $this->model->getDatabases();
	  
	  if( is_array($db) ){
	    foreach($db as $v){
		  echo '<div class="section">';
		  $this->escape(var_dump($v));
		  echo '</div>';
		}
	  }
    }

    function showScript()
    {
      ?>
      <?php
    }
  }
?>
