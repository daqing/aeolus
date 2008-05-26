<?php if( basename(__FILE__) == basename($_SERVER['REQUEST_URI'])){
        die('<h3>BAD REQUEST</h3>');}
    /**
	 * Default theme
	 *
	 * @author Qingcheng Zhang <kinch.zhang@gmail.com>
	 * @copyright Copyright (c) 2008-2009, CityGeneration, Inc. (http://citygeneration.com)
	 * @category template
	 *
	 */   
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 transitional//EN" 
    "http://www.w3.org/tr/xhtml1/DTD/xhtml1-transitional.dtd">
    <html><head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
	<!-- No cache -->
    <meta http-equiv="Pragma" content="no-cache" />
    <meta http-equiv="Expires" content="Sun, 6 Mar 2005 01:00:00 GMT" />
    <title><?php echo($this->title);?></title>
    <link href="<?php $this->showStyle();?>" rel="stylesheet" type="text/css" />
    <script type="text/javascript" src="<?php $this->showJquery();?>"></script>
    <script type="text/javascript" src="<?php $this->showJqueryCorner();?>"></script> 
    <script type="text/javascript"> 
      <?php $this->showScript();?> 
    </script>    
    </head>
    
    <body>
	<table width="960" border="0" cellspacing="0" cellpadding="0">
      <!-- Header -->
      <tr><td>
	    <div id="header"><?php $this->showHeader();?></div>
	  </td></tr>
      
      <!-- Navigator -->
	  <tr><td>
	    <div id="navigator"><?php $this->showNavigator()?></div>
	  </td></tr>

      <tr><td>
      <!-- Main -->
      <div id="main"> 
        <div id="notice"></div>
	    <div id="content"><?php $this->showContent();?></div>
      </div>           

      <!-- sidebar -->
      <div id="sidebar"><? $this->showSidebar();?></div>
      </td></tr>

      <!-- Footer -->
	  <tr><td>
	  <div id="footer">Powered by
	  <a href="http://code.google.com/p/aeolus/">Aeolus</a>
      &middot;&nbsp;&copy;&nbsp;Copyright 2008-2009,
	  <a href="http://citygeneration.com/">CityGeneration, Inc.</a>
	  &middot;&nbsp;All Rights Reserved   
      </div>
	  </td></tr>

    </table>
    </body>
</html>
