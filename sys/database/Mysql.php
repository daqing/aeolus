<?php if(! defined('APP_STARTED')){ die('<h3>BAD REQUEST.</h3>');}
  /**
   * Mysql class
   *
   * Database Driver for MySQL
   *
   * @author Kinch Zhang <kinch.zhang@gmail.com>
   *
   */
  
  class Mysql
  {
    /**
     * Resource
     *
     */
    var $res = null;  
   
    /**
     * Constructor
     *
     */
    function __construct()
    {
      if(!$this->res)
      {
        # Try to connect to the MySQL server
        AeolusFactory::loadOnce( AEOLUS_HOME.'/etc/database/mysql.php');
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
    function server_error()
    {
      ob_start();
      require(AEOLUS_ROOT.'/static/error/mysql_error.html');
      ob_end_flush();
      die();
    }
    
    /**
     * Query the database server
     *
     */
    function query($sql)
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
  }
?>
