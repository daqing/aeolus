<?php
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
    <title><?php echo($this->title);?></title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta http-equiv="Pragma" content="no-cache" />
    <meta http-equiv="Expires" content="Sun, 6 Mar 2005 01:00:00 GMT" />
    <link href="<?php $this->get_theme();;?>" rel="stylesheet" type="text/css" />
    <script type="text/javascript" src="<?php $this->get_jquery();?>"></script>
    <script type="text/javascript" src="<?php $this->get_jquery_corner();?>"></script>
    <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon" />
    <script type="text/javascript"> 
      $(function(){
        $("div#content").corner();      
        $("div#control").corner("top");       
      });           
     <?php $this->get_javascript();?>               
    </script>    
    </head>
    
    <body>
	<table id="citygeneration" width="960" border="0" cellspacing="0" cellpadding="0">
      <!-- Logo Area -->
      <tr><td><div id="logo_area"><?php $this->get_logo_area();?></div></td></tr>
      
      <!-- Notification -->
	  <tr><td><div id="notification" style="display:none;"></div></td></tr>

      <!-- Main Panel -->
      <tr>
      <td>
      <!-- Control Area -->
      <div id="control"><? $this->get_control();?></div>                        
      
      <!-- Main Panel-->
      <div id="main"> 
        <div id="spotlight"><?php $this->get_spotlight();?></div>
                  			    
        <div id="content">
          <div class="inner_content">	      
		  <div id="sections"><?php $this->get_sections();?></div>
          </div>
        </div>             
      </div>           
      </td>
      </tr>
      
      <!-- Footer -->
	  <tr><td><div id="footer">
	  <a href="http://code.google.com/p/aeolus/">Aeolus 0.1.50</a>
      <br/>Copyright (c) 2008-2009,
	  <a href="http://citygeneration.com/">CityGeneration, Inc.</a>
	  <br/>Some Rights Reserved   
      </div></td></tr>

    </table>
    </body>
</html>
