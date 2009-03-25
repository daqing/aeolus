<?php

    /* ExceptionAppNotRunningView class in exception module */
    class ExceptionAppNotRunningView extends AeoView
    {
        public function show_content()
        {
            echo '<div class="section">',
                '<p>App is not running</p>',
                '</div>';
        }

        public function show_script()
        {
            # echo '<script type="text/javascript" src=""></script>';
            ?>
            <script type="text/javascript">
                //alert('Hello, Aeolus');
            </script>
            <?php
        }
    }
?>
