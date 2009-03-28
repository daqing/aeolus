<?php

    /* IndexExceptionNormalView class in index module */

    class IndexExceptionNormalView extends AeoView
    {
        public function show_frame()
        {
            echo '<div class="section">Sorry, something went wrong.</div>',
                "\r\n<!--\r\nname: ", $this->data['name'],
                "\r\ntrace:\r\n", implode("\r\n", $this->data['trace']), "\r\n-->\r\n";
        }
    }
?>
