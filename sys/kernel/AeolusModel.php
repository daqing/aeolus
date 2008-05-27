<?php if(! defined('AEOLUS_STARTED')){ die('<h3>BAD REQUEST</h3>');}
  /**
   * AeolusModel class
   * 
   * @category kernel
   * @author Kinch Zhang <kinch.zhang@gmail.com>
   */

  class AeolusModel
  {
    /**
	 * Database driver
	 *
	 * @access private
	 *
	 */
    private static  $driver;

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
	function __construct($driver='mysql')
	{
	  if( null == self::$driver ){
	    $class = ucfirst($driver);
        AeolusFactory::loadFile( "database/$class.php");
	    self::$driver = new $class();
	    
		$this->data = array( 'result' => false,
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
	    $this->data['result'] = true;
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
        $this->data['result'] = true;
	    $this->data['affected'] = mysql_affected_rows();
		$this->data['set'] = array();
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
        $this->data['result'] = true;
	    $this->data['affected'] = mysql_affected_rows();
		$this->data['set'] = array();
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
	      $this->data['result'] = true;
		  $this->data['set'] = array();
	      while( $dataset = mysql_fetch_assoc($result) ){
	        $this->data['set'][] = $dataset;
	      }
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
