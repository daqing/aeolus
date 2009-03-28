<?php

    /* index_wildcard controller in index module */
    function index_wildcard($argv=null)
    {
        $v = Aeolus::newView('WildCard');

        $v->title = 'global wildcard handler';
        $v->data = $argv;

        $v->show();
    }
?>
