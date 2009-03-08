#! /usr/bin/php
<?php

    /*
     * Add application views
     */

    if ( 3 > $argc ) {
        echo "Usage: $argv[0] [MODULE] [VIEW]...\n";
        echo "Add VIEW(s) to a MODULE.\n\n";
        echo "Report bugs to <kinch.zhang@gmail.com>.\n";
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
                    $content = "<?php\n\n    /* $viewClass class in $module module */\n    ";
                    $content .= "class $viewClass extends AeoView\n    {";
                    $content .= "\n        public function show_sidebar()\n        {\n        }\n";
                    $content .= "\n        public function show_content()\n        {\n        }\n";
                    $content .= "\n        public function show_script()\n        {";
                    $content .= "\n            # echo '<script type=\"text/javascript\" ";
                    $content .= "src=\"\"></script>';";
                    $content .= "\n            ?>\n            <script type=\"text/javascript\">";
                    $content .= "\n                alert('Hello, Aeolus');";
                    $content .= "\n            </script>\n            <?php\n        }\n";
                    $content .= "    }\n?>";

                    if (FALSE === fwrite($res,$content))
                        echo "[ERROR] Can't write content to '$vpath'.\n";
                } else
                    echo "[ERROR] Can't open file $vpath to write.\n";
            }
        }
    } else
        echo "[ERROR] The directory '$gpath' doesn't exist or doesn't allow creating files.\n";
?>
