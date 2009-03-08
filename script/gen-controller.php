#! /usr/bin/php
<?php

    /*
     * Add application controllers
     */

    if (3 > $argc) {
        echo "Usage: $argv[0] [MODULE] [CONTROLLER]... \n";
        echo "Add CONTROLLER(s) to a MODULE.\n\n";
        echo "Report bugs to <kinch.zhang@gmail.com>.\n";
        exit(0);
    }

    define('AEOLUS_HOME', dirname(dirname(__FILE__)));

    $module = $argv[1];
    $con = array_slice($argv, 2);
    $gpath = AEOLUS_HOME.'/module/'.$module;

    if (file_exists($gpath) && is_writable($gpath)) {
        foreach ($con as $v) {
            $path = "$gpath/controller/{$module}_{$v}.php";
            if (!file_exists($path)) {
                if ($res = fopen($path,'w')) {
                    $content = "<?php\n\n    /* $v controller in $module module */\n    ";
                    $content .= "function {$module}_{$v}()\n    {\n        echo 'Hello,world!&nbsp;(From '$v'";
                    $content .= " controller in '$module' module)';\n  }\n?>";

                    if (FALSE === fwrite($res, $content))
                        echo "[ERROR] Can't write content to '$path'.\n";
                } else
                    echo "[ERROR] Can't open file '$path' to write.\n";

            }
        }
    } else
        echo "[ERROR] The directory '$gpath' doesn't exist or doesn't allow creating files.\n";

?
