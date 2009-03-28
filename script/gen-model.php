<?php

    /*
     * Add application models
     */

    if (3 > $argc) {
        echo "Usage: php $argv[0] MODULE MODEL...\n",
            "Add MODEL(s) to a MODULE.\n",
            "Options:\n",
            "\tMODULE - module name\n",
            "\tMODEL - one or more model names\n\n",
            "Report bugs to <kinch.zhang@gmail.com>.\n";
        exit(0);
    }

    define('AEOLUS_HOME', dirname(dirname(__FILE__)));

    $module = $argv[1];
    $models = array_slice($argv, 2);

    $gpath = AEOLUS_HOME.'/module/'.$module;

    if (file_exists( $gpath) && is_writable($gpath)) {
        foreach ($models as $m) {
            $modelClass = ucfirst($module) . $m . 'Model';
            $mpath = $gpath . '/model/' . $modelClass . '.php';
            if (!file_exists($mpath)) {
                if ($res = fopen($mpath, 'wb+')) {
                    $content = <<<MODELDEF
<?php

    /* $modelClass class in $module module */

    class $modelClass extends AeoModel
    {
        public function stub()
        {
        }
    }
?>
MODELDEF;
                    if (FALSE === fwrite($res, $content)) {
                        echo "[ERROR] Can't write content to '$mpath'.\n";
                    }
                } else {
                     echo "[ERROR] Can't open file '$mpath' to write.\n";
                }
            }
        }
    } else {
        echo "[ERROR] Directory '$module_path' doesn't exist or doesn't allow creating files.\n";
    }
?>
