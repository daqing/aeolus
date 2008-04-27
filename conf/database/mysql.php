<?php if( basename($_SERVER['REQUEST_URI']) == basename(__FILE__) ){
        die('Your request is invalid.<br/>System exited.');
	  }

    /**
     * Database connection configuration
     * 
     * @category etc 
     * @author Qingcheng Zhang <kinch.zhang@gmail.com>
     * @copyright Copyright (c) 2008-2009, CityGeneration, Inc. (http://www.citygeneration.com)
     * 
     * Subversion Keywords
     * 
     * $LastChangedDate$
     * $LastChangedBy$
     * $LastChangedRevision$
     * $URL$
     * $Id$
     * 
     */
    
    $mysql['host'] = 'localhost';
    $mysql['database'] = 'test';
    $mysql['user'] = 'test';
    $mysql['password'] = 'test';
    
?>
