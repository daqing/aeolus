<?php

    /* IndexWildCardView class in index module */
    class IndexWildCardView extends AeoView
    {
        public function show_frame()
        {
            echo '<div class="section">',
                '<p>Hi, this is the global wildcard url hander. <p>',
                '<p>You see this page because url "<em>', $this->data,
                '</em>" cannot be routed properly.</p></div>';
        }
    }
?>
