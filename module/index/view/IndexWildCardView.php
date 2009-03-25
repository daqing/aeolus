<?php

    /* IndexWildCardView class in index module */
    class IndexWildCardView extends AeoView
    {
        public function show_content()
        {
            echo '<div class="section">',
                '<p>Hi, this is the default wildcard url hander. <p>',
                '<p>You see this page because url "<em>', $this->data,
                '</em>" cannot be routed properly.</p></div>';
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
