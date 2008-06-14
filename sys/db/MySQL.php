<?php
  /**
   * MySQL class
   *
   * Database driver for MySQL
   *
   * @author Kinch Zhang <kinch.zhang@gmail.com>
   */
  
  class MySQL
  {
    /**
     * Resource
     *
	 * @access private
     */
    private $res = null;  
   
    /**
     * Constructor
     *
     */
    function __construct()
    {
      if(!$this->res)
      {
        # Try to connect to the MySQL server
        require( A_PREFIX.'etc/db/mysql.php');
        $this->res = @ mysql_connect("$host:$port", $user, $passwd);

        if(! $this->res ){
          # Fatal error: can't connect to database 
          $this->server_error();
        }
    
        if(! @mysql_select_db($schema, $this->res)){
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
     * Query the database server
     *
	 * @access public
     */
    public final function query($sql)
    {
      # Set encoding
      mysql_query("SET NAMES 'utf8'");
      mysql_query("SET CHARACTER SET 'utf8'");
      mysql_query("SET COLLATION_CONNECTION='utf8_general_ci'");
      
      if(!$result = mysql_query($sql,$this->res)){
        $this->server_error();
      }
        	 	
      return $result;
    }  

	public function getRes()
	{
	  return $this->res;
	}
  }
?>
