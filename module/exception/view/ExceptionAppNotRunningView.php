<?php

    /* ExceptionAppNotRunningView class in exception module */
    class ExceptionAppNotRunningView extends AeoView
    {
        public function show_frame()
        {
            echo '<div class="section">',
                '<p>Exception: App is not running</p>',
                '</div>';
        }
    }
?>
