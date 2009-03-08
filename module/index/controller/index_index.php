<?php

    /* index controller in index group */
    function index_index()
    {
        /*
        Aeolus::loadClass('AeoCache');
        if (!$data = AeoCache::fetch('date')) {
            $m = Aeolus::newModel('WelcomeModel');
            $data = $m->get_msg();

            AeoCache::store($data, 'date');
        }
        */

        $m = Aeolus::newModel('Welcome');

        $v = Aeolus::newView('IndexView');
        $v->title = 'Home';
        $v->data = $m->getMessage();

        $v->show();
    }
?>
