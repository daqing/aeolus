<?php

    /* index_index controller in index group */
    function index_index($argv=null)
    {
        $m = Aeolus::newModel('Welcome');

        $v = Aeolus::newView('Index');
        $v->title = 'Home';
        $v->data = $m->getMessage();

        $v->show();
    }
?>
