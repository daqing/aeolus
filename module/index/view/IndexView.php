<?php

    /* IndexView view in index group */
    class IndexView extends AeoView
    {
        public function show_sidebar()
        {
            echo 'Aeolus home page';
        }

        public function show_content()
        {
            Aeolus::loadClass('AeoGuard');
            echo '<div class="section">',
                 '<p>Aeolus is an open-source PHP framework ',
                 'designed for productive Web development.</p>',
                 '</div>',
                 '<div class="section">';
            echo AeoGuard::pf($this->data);
            echo '</div>';
        }

        public function show_script()
        {
          ?>
          <?php
        }
    }
?>
