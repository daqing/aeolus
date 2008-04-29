<?php
    /**
     * Aeolus Model class
     * 
     * @category kernel
     * @author Qingcheng Zhang <kinch.zhang@gmail.com>
     * @copyright Copyright (c) 2008-2009, CityGeneration, Inc. (http://www.citygeneration.com)
     * 
     * Subversion Keywords
     * 
     * $LastChangedBy$
     * $LastChangedRevision$
     * $URL$
     * $Id$
     *
     */

	class AeolusModel
	{
        /**
		 * Database driver
		 *
		 */
		var $driver = null;
		
		/**
    	 * Cache object
    	 *
    	 */
    	var $cache = null;
		
		/**
		 * Constructor
		 *
		 */
		function Model($driver='mysql')
		{
		    if( 'mysql' == $driver )
			{
			    aeolus_load(AEOLUS_ROOT.'/kernel/database/Mysql.php');
				$this->driver = new Mysql();
				$this->data = array(
				    'result' => false,
				    'affected' => 0,
				    'lastInsertId' => null,
				    'set' => array()
				);			
			}
			
			require_once(AEOLUS_ROOT.'/kernel/cache/FileCache.php');
    	    
    	    if( !$this->cache )
    	    {
    	        $this->cache = new FileCache(300);
    	    }
		}

		/**
		 * Insert data to database
		 *
		 */
		function insert($sql)
		{
		    $result = $this->driver->query($sql);
		    
		    if($result)
		    {
		        $this->data['result'] = true;
		        $this->data['affected'] = mysql_affected_rows();
		        $this->data['lastInsertId'] = mysql_insert_id();
		    }
		    
		    return $this->data;
		}
		
		/**
		 * Update database tables
		 *
		 */
		function update($sql)
		{
		    if($this->driver->query($sql))
		    {
		        $this->data['result'] = true;
		        $this->data['affected'] = mysql_affected_rows();
		    }
		    
		    return $this->data;
		}
		
		/**
		 * Delete data from database tables
		 *
		 */
		function delete($sql)
		{
		    if($this->driver->query($sql))
		    {
		        $this->data['result'] = true;
		        $this->data['affected'] = mysql_affected_rows();
		    }
		    
		    return $this->data;
		}
		
		/**
		 * Select data from database
		 *
		 */
		function select($sql)
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
		 */
		function escape($v)
		{
		  return mysql_real_escape_string($v,$this->driver->res);
		}	
	}
?>
