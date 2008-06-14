<?php
  /**
   * AModel class
   * 
   * @category kernel
   * @author Kinch Zhang <kinch.zhang@gmail.com>
   */

  class AModel
  {
    /**
	 * Database driver
	 *
	 * @access private
	 *
	 */
    private static $driver = null;

	/**
	 * Data set
	 *
	 * @access private
	 *
	 */
	private $data;
	
	/**
	 * Constructor
	 *
	 */
	function __construct()
	{
	  if( null == self::$driver ){
		# Load configurations
		require( A_PREFIX.'etc/db/driver.php');

		# Load driver class
        A::ld( "db/$driver");
	    self::$driver = new $driver();
	    
		$this->data = array(
		  'affected' => 0,
		  'lastInsertId' => 0,
		  'set' => array()
		);
	  }
	}

	/**
	 * Insert data to database
	 *
	 */
	public final function insert($sql)
	{
	  $result = self::$driver->query($sql);
	  
	  if($result){
	    $this->data['affected'] = mysql_affected_rows();
	    $this->data['lastInsertId'] = mysql_insert_id();
		$this->data['set'] = array();
	  }
	  
	  return $this->data;
	}
	
	/**
	 * Update database tables
	 *
	 * @access public
	 * @param string $sql SQL query string
	 * @return array $data the result array
	 *
	 */
	public final function update($sql)
	{
	  if(self::$driver->query($sql)){
	    $this->data['affected'] = mysql_affected_rows();
	  }
	  
	  return $this->data;
	}
	
	/**
	 * Delete data from database tables
	 *
	 * @access public
	 * @param string $sql SQL query string
	 * @return array $data the result data
	 *
	 */
	public final function delete($sql)
	{
	  if(self::$driver->query($sql)){
	    $this->data['affected'] = mysql_affected_rows();
	  }
	  
	  return $this->data;
	}
	
	/**
	 * Select data from database
	 *
	 * @access public
	 * @param string $sql SQL query string
	 * @return array $data the result data
	 *
	 */
	public final function select($sql)
	{			
	  if($result = self::$driver->query($sql)){	        
	    $this->data['affected'] = mysql_num_rows($result);
	      
	    if( $this->data['affected'] > 0 ){
	      while( $dataset = mysql_fetch_assoc($result) ){
	        $this->data['set'][] = $dataset;
	      }

		  $dataset = null;
	    }
	  }
	  
	  return $this->data;
	}
	
	/**
	 * Escape a variable
	 * 
	 * @access public
	 * @param string $value the string to be escaped
	 * @return string $escaped the escaped string
	 *
	 */
	public final function escape($value)
	{
	  return mysql_real_escape_string($value,self::$driver->getRes());
	}	

  }
?>
