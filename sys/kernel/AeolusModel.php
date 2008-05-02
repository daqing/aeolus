<?php if(! defined('APP_STARTED')){ die('<h3>BAD REQUEST.</h3>');}
  /**
   * Aeolus Model class
   * 
   * @category kernel
   * @author Kinch Zhang <kinch.zhang@gmail.com>
   *
   */

  class AeolusModel
  {
    /**
	 * Database driver
	 *
	 * @access private
	 *
	 */
    private $driver;

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
	  $class = ucfirst($driver);
      AeolusFactory::loadOnce( "database/$class.php");
	  $this->driver = new $class();
	  $this->data = array( 'result' => false,
	                       'affected' => 0,
						   'lastInsertId' => 0,
						   'set' => array()
						  );
	}

	/**
	 * Insert data to database
	 *
	 */
	public function insert($sql)
	{
	  $result = $this->driver->query($sql);
	  
	  if($result){
	    $this->data['result'] = true;
	    $this->data['affected'] = mysql_affected_rows();
	    $this->data['lastInsertId'] = mysql_insert_id();
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
	public function update($sql)
	{
	  if($this->driver->query($sql)){
        $this->data['result'] = true;
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
	public function delete($sql)
	{
	  if($this->driver->query($sql)){
        $this->data['result'] = true;
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
	public function select($sql)
	{			
	  if($result = $this->driver->query($sql)){	        
	    $this->data['affected'] = mysql_num_rows($result);
	      
	    if( $this->data['affected'] > 0 ){
	      $this->data['result'] = true;
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
	 * @access private
	 * @param string $value the string to be escaped
	 *
	 */
	private function escape($value)
	{
	  return mysql_real_escape_string($value,$this->driver->res);
	}	
  }
?>
