<?php
    /**
	 * Aeolus template
	 *
	 * @author Kinch Zhang <kinch.zhang@gmail.com>
	 */   
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 transitional//EN" 
    "http://www.w3.org/tr/xhtml1/DTD/xhtml1-transitional.dtd">
    <html><head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
	<!-- Cache control -->
    <title><?php echo($this->title);?></title>
    <link href="<?php $this->show_style();?>" rel="stylesheet" type="text/css" />
    <script type="text/javascript" src="<?php $this->show_jquery();?>"></script>
    <script type="text/javascript" src="<?php $this->show_jcorner();?>"></script> 
    </head>
    
    <body>
	<table width="960" border="0" cellspacing="0" cellpadding="0">
      <!-- Top -->
      <tr><td>
	    <div id="top"><?php $this->show_top();?></div>
	  </td></tr>
      
      <!-- Navigator -->
	  <tr><td>
	    <div id="nav"><?php $this->show_nav()?></div>
	  </td></tr>

     <!-- Middle -->
      <tr><td>
        <!-- Main -->
        <div id="main"> 
          <div id="notice"></div>
	      <div id="content"><?php $this->show_content();?></div>
        </div>           

        <!-- sidebar -->
        <div id="sidebar"><? $this->show_sidebar();?></div>
      </td></tr>

      <!-- Bottom -->
	  <tr><td>
	    <div id="bottom">Powered by
		  <a href="http://code.google.com/p/aeolus/">Aeolus</a>
		  &middot;&nbsp;&copy;&nbsp;Copyright 2008-2009,
		  <a href="http://citygeneration.com/">CityGeneration, Inc.</a>
		  &middot;&nbsp;All Rights Reserved   
        </div>
	  </td></tr>
    </table>

	<!-- JavaScript -->
    <script type="text/javascript"> 
	  $(function(){
		// Base URL
	    var base = '<?php echo URL_BASE;?>';

		$("#nav").corner();

        <?php $this->show_script();?> 
	  });
    </script>    
</body></html>
