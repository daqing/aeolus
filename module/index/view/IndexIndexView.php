<?php

    /* IndexIndexView class in index module */
    class IndexIndexView extends AeoView
    {
        public function show_content()
        {
            echo '<div class="section">',
                'Aeolus is an open-source PHP framework designed for productive Web development.',
                '</div>',
                '<div class="section">',
                $this->data,
                '</div>';

        }

        public function show_script()
        {
            parent::show_script();
            # echo '<script type="text/javascript" src=""></script>';
            ?>
            <script type="text/javascript">
                //alert('Hello, Aeolus');
            </script>
            <?php
        }
    }
?>
