<?php
  /**
   * simpletest controller in 'demo' module
   *
   */

  require_once 'SimpleTest/autorun.php';
  require_once 'Log.php';
  
  function index()
  {
    $test = new LoggingTest();
	$test->run(new HtmlReporter());
	die();
  }

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
?>
