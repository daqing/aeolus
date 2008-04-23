<?php if( basename($_SERVER['REQUEST_URI']) == basename(__FILE__)){
        die('Your request is invalid.<br/>System exited.');
	  }

    /**
     * URL routing rules
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

    /**
     * The key in $rule is the 'group' name , and the value array defines 
     * all the valid controllers in that group. For instance, if you want to 
     * add a group called 'foo',and define a controller called 'bar',you can 
     * add a line like the following:
     *     $rule['foo'] = array('bar');
     * 
     */
    
    # The subdir URL where you can see your home page
    # Note : you'd include the leading slash in this base URL
    $rule['subdir'] = '/git-repo/aeolus';
 
    # ======================================================= #
    # Don't edit below unless you know what you're doing. ;-p
    # ======================================================= #
    $rule['index'] = array('index','topic','user');
    $rule['ajax'] = array('topic');
	$rule['install'] = array('index','add');
	$rule['admin'] = array('index');
	$rule['sandbox'] = array('index');
    
?>
