<?php

    /* alpha_index controller in alpha module */

    function alpha_index($argv=null)
    {
        $v = Aeolus::newView('Index');

        $v->title = 'alpha';

        $v->show();
    }
?>
