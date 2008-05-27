<?php if(! defined('AEOLUS_STARTED')){ die('<h3>BAD REQUEST</h3>');}
  /**
   * Mysql class
   *
   * Database Driver for MySQL
   *
   * @author Kinch Zhang <kinch.zhang@gmail.com>
   */
  
  class Mysql
  {
    /**
     * Resource
     *
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
        AeolusFactory::loadFile( AEOLUS_HOME.'/etc/database/mysql.php');
        $this->res = MYSQL_connect(MYSQL_HOST.':'.MYSQL_PORT,
  	                             MYSQL_USER,
  								 MYSQL_PASSWORD);
  
        if( !$this->res ){
          # Fatal error: can't connect to database 
          $this->server_error();
        }
    
        if( !mysql_select_db(MYSQL_SCHEMA,$this->res)){
          $this->server_error();
        }
      }
    }
  
    /**
     * Display server error and exit
     *
     */
    private function server_error()
    {
      ob_start();
      require(AEOLUS_HOME.'/pub/error/mysql_error.html');
      ob_end_flush();
      die();
    }
    
    /**
     * Query the database server
     *
     */
    public function query($sql)
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
