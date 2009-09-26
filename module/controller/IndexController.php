<?php

class IndexController
{
    public function index($sys, $argv)
    {
        echo 'it works';
    }

    public function fallback($sys, $url)
    {
        echo 'fallback from url: ' . $url;
    }
}

?>
