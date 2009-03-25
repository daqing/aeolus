<?php

    /* route_failed controller in exception module */
    function exception_route_failed($argv)
    {
        $v = Aeolus::newView('RouteFailed');

        $v->title = 'RouteFailed';

        $v->data = $argv;

        $v->show();
    }
?>
