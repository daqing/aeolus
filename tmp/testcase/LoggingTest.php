<?php
  /**
   * LoggingTest testcase class 
   *
   */

  require_once 'SimpleTest/autorun.php';
  require_once 'Log.php';

  class LoggingTest extends UnitTestCase
  {
    function testCreatingNewFile()
	{
	  @unlink('/tmp/test.log');
	  $log = new Log('/tmp/test.log');
	  $log->message('Should write this to a file');
	  $this->assertTrue(file_exists('/tmp/test.log'));
	}
  }
