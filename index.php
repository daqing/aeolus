<?php

    # global environment variable
    global $env;

    $env['module'] = 'index';
    $env['controller'] = 'index';

    # handy constant to use
    define('A_PREFIX', dirname(__FILE__) . '/');

    require 'config/system/app.php';
    require 'system/bootstrap.php';
    require 'Aeolus.php';
    require 'AeoException.php';
    require 'kernel/AeoFront.php';

    if (! APP_ENABLED) {
        new AeoException(
            array(
                'name' => 'APPNotRunning',
                'detail' => 'app not running',
                'runtime' => array(),
            )
        );
    }

    # load front controller
    $front = new AeoFront();

    $url = isset($_GET['url']) ? $_GET['url'] : $_SERVER['REQUEST_URI'];
    $front->run($url);

?>
