<?php

    /* IndexExceptionDebugView class in index module */

    class IndexExceptionDebugView extends AeoView
    {
        public function show_frame()
        {
            echo '<div class="section">An exception has been thrown</div>',
                '<div class="section">',
                '<h3>Exception: <span>', $this->data['name'], 'Exception</span></h3>',
                '</div><div class="section">',
                '<h3>Detail:</h3><p>', $this->data['detail'], '</p>',
                '</div><div class="section">',
                '<h3>Runtime:</h3><p>', $this->data['runtime'], '</p>',
                '</div><div class="section">',
                '<h3>Trace:</h3><p><ul><li>', implode('<li>', $this->data['trace']), '</ul></p>',
                '</div>';
        }
    }
?>
