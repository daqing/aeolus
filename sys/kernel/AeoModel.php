<?php
  /*
   * Model class
   */

  class AeoModel
  {
    private static $driver = null;

	private $data = array();
	
	function __construct()
	{
	  if (null == self::$driver) {
		require A_PREFIX.'etc/db/driver.php';

        Aeo::ld("db/$driver");
	    self::$driver = new $driver();
	    
		$this->data = array(
		  'affected' => 0,
		  'lastInsertId' => 0,
		  'set' => array()
		);
	  }
	}

	public final function insert($sql)
	{
	  if (self::$driver->query($sql)) {
	    $this->data['affected'] = mysql_affected_rows();
	    $this->data['lastInsertId'] = mysql_insert_id();
	  }
	  return $this->data;
	}
	
	public final function update($sql)
	{
	  if (self::$driver->query($sql))
	    $this->data['affected'] = mysql_affected_rows();

	  return $this->data;
	}
	
	public final function delete($sql)
	{
	  if (self::$driver->query($sql))
	    $this->data['affected'] = mysql_affected_rows();
	  
	  return $this->data;
	}
	
	public final function select($sql)
	{			
	  if ($result = self::$driver->query($sql)) {	        
	    $this->data['affected'] = mysql_num_rows($result);
	    if ($this->data['affected'] > 0) {
		  /* Fetch data */
	      while ($dataset = mysql_fetch_assoc($result))
	        $this->data['set'][] = $dataset;

		  $dataset = null;
	    }
	  }
	  return $this->data;
	}
	
	public final function escape($v)
	{
	  return mysql_real_escape_string($v,self::$driver->get_link());
	}	
  }
?>
