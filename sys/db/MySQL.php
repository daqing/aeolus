<?php
  /**
   * MySQL class
   *
   * @author Kinch Zhang <kinch.zhang@gmail.com>
   */
  
  class MySQL
  {
    /**
     * Link identifier
     *
	 * @access private
     */
    private $link = null;  
   
    /**
     * Constructor
     *
     */
    function __construct()
    {
      if(! $this->link )
      {
        # Try to connect to the MySQL server
        require( A_PREFIX.'etc/db/mysql.php');
        $this->link = @ mysql_connect("$host:$port", $user, $passwd);

        if(! $this->link ){
          # Fatal error: can't connect to database 
          $this->server_error();
        }
    
        if(! @ mysql_select_db($schema, $this->link) ){
          $this->server_error();
        }
      }
    }
  
    /**
     * Display server error and exit
     *
	 * @access private
     */
    private function server_error()
    {
      ob_start();
      require(A_PREFIX.'pub/error/mysql_error.html');
      ob_end_flush();
      die();
    }
    
    /**
     * Send a MySQL query
     *
	 * @access public
	 * @param string $sql The SQL query
	 * @return resource $result The query result
     */
    public final function query($sql)
    {
      # Set encoding
      mysql_query("SET NAMES 'utf8'");
      mysql_query("SET CHARACTER SET 'utf8'");
      mysql_query("SET COLLATION_CONNECTION='utf8_general_ci'");
      
      if(! $result = mysql_query($sql,$this->link) ){
        $this->server_error();
      }
        	 	
      return $result;
    }  

	/**
	 * Get the link identifier
	 *
	 * @access public
	 */
	public function get_link()
	{
	  return $this->link;
	}
  }
?>
