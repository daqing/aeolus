<?php

/*
 * View class
 */

class AeoView
{
    public $title = 'defaultTitle';

    public $data = null;

    /* Render a given template */
    public function show()
    {
        ob_start();
        require A_PREFIX . 'public/theme/' . APP_TPL . '.php';
        ob_end_flush();
        exit(0);
    }

    public function show_head()
    {
        echo '<link type="text/css" href="' , SUB_DIR, 'public/theme/',
            APP_STYLE, '.css" rel="stylesheet" />',
            '<link type="text/css" href="', SUB_DIR,
            'public/script/jquery-ui-1.7.1.css" rel="stylesheet" />';
    }

    public function show_style()
    {
    }

    public function show_top_panel()
    {
        echo '<div class="userbox corner"><a href="', BASE_URL, 'login/" target="_self">登录</a>&middot;',
            '<a href="', BASE_URL, 'register/" target="_self">注册</a></div>',
            '<a id="logo" href="', BASE_URL, '" target="_self"><img src="', SUB_DIR, 'public/image/logo.gif" width="165" height="34" /></a>';

        global $env;

        $h = Aeolus::loadHelper('apps', 'index');

        $h($env['controller']);
    }

    public function show_frame()
    {
        echo '<div class="section">',
            '<p>I am a template.</p>',
            '</div>';
    }

    public function show_bottom_panel()
    {
        echo 'Powered by <a href="http://github.com/kinch/aeolus/tree/master/">Aeolus</a>',
            '&nbsp;&middot;&nbsp;&copy;&nbsp;Copyright 2008-2009,&nbsp;',
            '<a href="mailto:kinch.zhang@gmail.com">Kinch Zhang</a>',
            '&nbsp;&middot;&nbsp;All Rights Reserved';
    }

    public function show_script()
    {
        echo '<script type="text/javascript" src="',
            SUB_DIR, 'public/script/jquery-1.3.2.js"></script>',
            '<script type="text/javascript" src="',
            SUB_DIR, 'public/script/jquery.corner-1.92.js"></script>',
            '<script type="text/javascript" src="',
            SUB_DIR, 'public/script/jquery-ui-1.7.1.js"></script>';
        ?>
        <script type="text/javascript">
        $(function() {
            //$(".section").corner('bottom');
        });
        </script>
        <?php
    }

    public function html_safe($v)
    {
        return htmlentities($v, ENT_QUOTES, 'UTF-8');
    }
}

?>
