<?php

    /*
     * Add application views
     */

    if ( 3 > $argc ) {
        echo "Usage: php $argv[0] MODULE VIEW...\n",
            "Add VIEW(s) to a MODULE.\n",
            "Options:\n",
            "\tMODULE - module name\n",
            "\tVIEW - one or more view names\n\n",
            "Report bugs to <kinch.zhang@gmail.com>.\n";
        exit(0);
    }

    define('AEOLUS_HOME', dirname(dirname(__FILE__)));

    $module = $argv[1];
    $views = array_slice($argv, 2);
    $gpath = AEOLUS_HOME.'/module/'.$module;

    if (file_exists( $gpath ) && is_writable($gpath)) {
        foreach ($views as $v) {
            $viewClass = ucfirst($module) . $v . 'View';
            $vpath = $gpath.'/view/'.$viewClass.'.php';
            if (!file_exists($vpath)) {
                if ($res = fopen($vpath, 'w')) {
                    $content = <<<VIEWDEF
<?php

    /* $viewClass class in $module module */

    class $viewClass extends AeoView
    {
        public function show_frame()
        {
        }
    }
?>
VIEWDEF;
                if (FALSE === fwrite($res,$content))
                        echo "[ERROR] Can't write content to '$vpath'.\n";
                } else
                    echo "[ERROR] Can't open file $vpath to write.\n";
            }
        }
    } else
        echo "[ERROR] The directory '$gpath' doesn't exist or doesn't allow creating files.\n";
?>
