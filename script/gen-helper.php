<?php

    /*
     * Add application helpers
     */

    if (3 > $argc) {
        echo "Usage: php $argv[0] MODULE HELPER...\n",
            "Add HELPER(s) to a MODULE.\n",
            "Options:\n",
            "\tMODULE - module name\n",
            "\tHELPER - one or more helper names\n\n",
            "Report bugs to <kinch.zhang@gmail.com>.\n";
        exit(0);
    }

    define('AEOLUS_HOME', dirname(dirname(__FILE__)));

    $module = $argv[1];
    $helpers = array_slice($argv, 2);
    $gpath = AEOLUS_HOME.'/module/'.$module;

    foreach ($helpers as $h) {
        $hpath = $gpath.'/helper/'.$h.'.php';

        if (file_exists( $gpath ) && is_writable($gpath)) {
            if (!file_exists($hpath)) {
                  if ($res = fopen($hpath, 'w')) {
                      $content = "<?php\n\n  /* $h helper in $module module */\n  ";
                      $content .= "function $h()\n  {\n    ?>\n    <div>Change Me!</div>";
                    $content .= "\n    <?php\n  }\n?>";
        
                    if (FALSE === fwrite($res, $content))
                        echo "[ERROR] Can't write content $content to file: $hpath.\n";
                } else {
                    echo "[ERROR] Can't open file $hpath to write.\n";
                }
            }
        } else {
            echo "[ERROR] Directory '$gpath' doesn't exist or doesn't allow creating files.\n";
        }
      }

?>
