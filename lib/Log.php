<?php
  /**
   * Log class 
   *
   */

  class Log
  {
    var $_file_path;

    function Log($file_path)
	{
	  $this->_file_path = $file_path;
	}

	function message($message)
	{
      $file = fopen($this->_file_path, 'a');
	  fwrite($file,$message,"\n");
	  fclose($file);
	}

  }

?>
