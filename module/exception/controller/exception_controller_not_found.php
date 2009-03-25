<?php

    /* controller_not_found controller in exception module */
    function exception_controller_not_found($argv=null)
    {
        $v = Aeolus::newView('ControllerNotFound');

        $v->title = 'ControllerNotFound';
        $v->data = $argv;

        $v->show();
    }
?>
