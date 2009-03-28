<?php

    /* index_apps_helper in index module */

    function index_apps_helper($argv=null)
    {
        echo '<a class="app" href="', BASE_URL, 'talk" target="_self">群组</a>',
            '<a class="app" href="', BASE_URL, 'blog" target="_self">博客</a>';
    }
?>
