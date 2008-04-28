<?php
  /**
   * KernelTest testcase class 
   *
   */

  require_once 'SimpleTest/autorun.php';

  class KernelTest extends UnitTestCase
  {
    function testAeolusRequestProcess()
	{
      require_once 'core/a_process.php';
	  $expect = array('module' => 'error',
	                  'controller' => 'index',
					  'action' => 'index',
					  'argc' => 0,
					  'argv' => array()
					 );
	  
	  $this->assertEqual($expect,a_process('/aeolus/foobar/'));
	  $expect = array('module' => 'demo',
	                  'controller' => 'zend',
					  'action' => 'index',
					  'argc' => 0,
					  'argv' => array()
					 );
	  $this->assertEqual($expect,a_process('/aeolus/demo/zend/'));

	}

  }
?>
