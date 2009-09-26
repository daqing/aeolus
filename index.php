<?php

    session_start();

    $dir = dirname(__FILE__) . '/';
    # set include_path
    ini_set(
        'include_path',
        $dir . 'plugin/' . PATH_SEPARATOR .
        $dir . 'system/' . PATH_SEPARATOR .
        ini_get('include_path')
    );

    require 'AeoLib.php';
    require 'AeoSystem.php';

    $sys = new AeoSystem();

    $sys->boot();
?>
