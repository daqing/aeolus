<?php

    /* exception_app_not_running controller in exception module */
    function exception_app_not_running($argv=null)
    {
        $v = Aeolus::newView('AppNotRunning');

        $v->title = 'AppNotRunning';

        $v->show();
    }
?>
