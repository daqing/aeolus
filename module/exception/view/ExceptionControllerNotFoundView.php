<?php

    /* ExceptionControllerNotFoundView class in exception module */
    class ExceptionControllerNotFoundView extends AeoView
    {
        public function show_sidebar()
        {
            echo 'error';
        }

        public function show_content()
        {
            echo '<div class="section">',
                '<p>An error occurred: </p>',
                '<ul>',
                '<li>controller <em>', $this->data['controller'], '</em>',
                ' in module <em>', $this->data['module'], '</em> not found.</li>',
                '</ul>',
                '</div>';
        }
    }
?>
