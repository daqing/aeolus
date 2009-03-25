<?php
    /**
     * Default template for Aeolus system
     *
     * @author Kinch Zhang <kinch.zhang@gmail.com>
     */
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 transitional//EN" 
    "http://www.w3.org/tr/xhtml1/DTD/xhtml1-transitional.dtd">
    <html><head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title><?php echo $this->title;?></title>
    <link href="<?php $this->show_style();?>" rel="stylesheet" type="text/css" />
    </head>

    <body id="trackgeek">
    <div id="main">
        <!-- Top -->
        <div id="top"><?php $this->show_top();?></div>

        <!-- Navigator -->
        <div id="nav"><?php $this->show_nav()?></div>

        <div id="notice"></div>
        <div id="content"><?php $this->show_content();?></div>

        <!-- Bottom -->
        <div id="bottom">Powered by
           <a href="http://github.com/kinch/aeolus/tree/master/">Aeolus</a>
           &middot;&nbsp;&copy;&nbsp;Copyright 2008-2009,
           <a href="mailto:kinch.zhang@gmail.com">Kinch Zhang (a.k.a daqing)</a>
           &middot;&nbsp;All Rights Reserved   
        </div>
    </div>

    <!-- JavaScript -->
    <script type="text/javascript" src="<?php $this->show_jquery();?>"></script>
    <script type="text/javascript" src="<?php $this->show_jcorner();?>"></script>
    <?php $this->show_script();?> 
    <script type="text/javascript"> 
      $(function(){
            $("#sidebar").corner();
            $(".section").corner();
      });
    </script>
</body></html>
