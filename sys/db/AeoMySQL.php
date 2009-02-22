<?php
  /*
   * Database driver for MySQL 5.0+
   */
  
  class AeoMySQL
  {
    private $link = null;  
   
    function __construct()
    {
      if (!$this->link) {
        require A_PREFIX . 'config/db/mysql.php';
        $this->link = @mysql_connect("$host:$port", $user, $passwd);
        if (! $this->link) 
          $this->server_error();
    
        if (! @mysql_select_db($schema, $this->link))
          $this->server_error();
      }
    }
  
    private function server_error()
    {
	  if (APP_DEBUG) {
        ob_start();
        require A_PREFIX . 'public/error/mysql_error.html';
        ob_end_flush();
	    exit(0);
	  } else {
	    exit('<h3>Internal Database Error</h3>');
	  }
    }
    
    public final function query($sql)
    {
      /* Set encoding */
      mysql_query("SET NAMES 'utf8'");
      mysql_query("SET CHARACTER SET 'utf8'");
      mysql_query("SET COLLATION_CONNECTION='utf8_general_ci'");
      
      if (!$result = mysql_query($sql, $this->link))
        $this->server_error();

      return $result;
    }  

	public function get_link()
	{
	  return $this->link;
	}
  }
?>
