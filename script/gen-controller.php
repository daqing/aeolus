<?php

    /*
     * Add application controllers
     */

    if (3 > $argc) {
        echo "Usage: php $argv[0] MODULE CONTROLLER...\n",
            "Add CONTROLLER(s) to a MODULE.\n",
            "Options:\n",
            "\tMODULE - module name\n",
            "\tCONTROLLER - one or more controller names\n\n",
            "Report bugs to <kinch.zhang@gmail.com>.\n";
        exit(0);
    }

    define('AEOLUS_HOME', dirname(dirname(__FILE__)));

    $module = $argv[1];
    $con = array_slice($argv, 2);
    $gpath = AEOLUS_HOME.'/module/'.$module;

    if (file_exists($gpath) && is_writable($gpath)) {
        foreach ($con as $v) {
            $controller = $module . '_' . $v;
            $path = "$gpath/controller/$controller.php";
            if (!file_exists($path)) {
                if ($res = fopen($path,'w')) {
                    $content = <<<CONTROLLERDEF
<?php

    /* $controller controller in $module module */

    function $controller(\$argv=null)
    {
        echo 'This is <em>$controller</em> controller in <strong>$module</strong> module.';
    }
?>
CONTROLLERDEF;
                if (FALSE === fwrite($res, $content))
                        echo "[ERROR] Can't write content to '$path'.\n";
                } else
                    echo "[ERROR] Can't open file '$path' to write.\n";

            }
        }
    } else {
        echo "[ERROR] The directory '$gpath' doesn't exist or doesn't allow creating files.\n";
    }

?>
