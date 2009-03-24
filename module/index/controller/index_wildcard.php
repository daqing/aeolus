<?php

    /* wildcard controller in index module */
    function index_wildcard($argv)
    {
        $v = Aeolus::newView('WildCard');

        $v->title = 'wildcard handler';
        $v->data = $argv;

        $v->show();
    }
?>
