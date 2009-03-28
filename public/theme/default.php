<!-- default template -->

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 transitional//EN"
    "http://www.w3.org/tr/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
    <head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title><?php echo $this->title; ?></title>
    <?php $this->show_head() ?>
    </head>
    <body>
    <div id="main">
        <div id="top_panel">
        <?php $this->show_top_panel(); ?>
        </div>

        <div id="frame">
        <?php $this->show_frame(); ?>
        </div>

        <div id="bottom_panel">
            <?php $this->show_bottom_panel(); ?>
        </div>

        <?php $this->show_script();?>
    </div>
    </body>
</html>
