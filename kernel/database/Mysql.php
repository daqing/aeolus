<?php
    /**
	 * Mysql Database Driver
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
		function Mysql()
		{
		    if(!$this->res)
			{
		        /* Try to connect to the MySQL server */
			    require(AEOLUS_ROOT.'/conf/database/mysql.php');
           
				$this->res = mysql_connect($mysql['host'],$mysql['user'],$mysql['password']);

    			if( !$this->res ) 
    			{
    			    /* Fatal error: can't connect to database */
    				$this->server_error();
    			}
    
    			if( !mysql_select_db($mysql['database'],$this->res) )
    			{
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
		    /* Connection set */
		    mysql_query("SET NAMES 'utf8'");
			mysql_query("SET CHARACTER SET 'utf8'");
			mysql_query("SET COLLATION_CONNECTION='utf8_general_ci'");
			
			if($result = mysql_query($sql,$this->res))
			{
			    return $result;
			}else{
			    $this->server_error();
			}
		}	
	}
?>


