<?php

    /* index controller in index group */
    function index_index($argv)
    {
        $m = Aeolus::newModel('Welcome');

        $v = Aeolus::newView('Index');
        $v->title = 'Home';
        $v->data = $m->getMessage();

        $v->show();
    }
?>
