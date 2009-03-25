<?php

    /* view_not_exists controller in exception module */
    function exception_view_not_exists($argv=null)
    {
        $v = Aeolus::newView('ViewNotExists');

        $v->title = 'ViewNotExists';

        $v->show();
    }
?>
