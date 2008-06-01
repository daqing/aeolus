<?php

  # 'IndexModel' model in 'index' group
  class IndexModel extends AModel
  {
    public function getData()
    {
	  $message = 'Welcome to Aeolus.<br/><br/>';
	  $message .= 'Cached at '.date('H:i:s');

	  return $message;
    }
  } 
?>
