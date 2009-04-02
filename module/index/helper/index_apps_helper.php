<?php

    /* index_apps_helper in index module */

    function index_apps_helper($argv=null)
    {
        $apps = array(
            'talk' => '群组',
            'blog' => '博客',
            'friend' => '好友',
            'mail' => '消息',
            'event' => '活动',
            'my' => '个人中心',
        );

        foreach ($apps as $k => $v)
        {
            echo '<a class="';
            if ($k == $argv)
                echo 'current';
            else
                echo 'app';

            echo '" href="', BASE_URL, $k, '" target="_self">', $v, '</a>';
        }
    }
?>
