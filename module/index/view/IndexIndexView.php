<?php

    /* IndexIndexView class in index module */
    class IndexIndexView extends AeoView
    {
        public function show_frame()
        {
            echo '<div class="section">',
                'Aeolus is an open-source PHP framework designed for productive Web development.',
                '</div>',
                '<div class="section">',
                $this->data,
                '</div>';
        }

    }
?>
