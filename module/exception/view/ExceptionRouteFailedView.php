<?php

    /* ExceptionRouteFailedView class in exception module */
    class ExceptionRouteFailedView extends AeoView
    {
        public function show_sidebar()
        {
            echo 'route failed';
        }

        public function show_content()
        {
            echo '<div class="section">',
                '<h2>Error: routing  url failed</h2>',
                '<p>The url: [', $this->data['url'], '] can not be routed properly.</p>',
                '</div><div class="section">',
                '<p>Routing result:</p>',
                '<ul>',
                '<li>module: ', $this->data['module'], '</li>',
                '<li>controller: ', $this->data['controller'], '</li>',
                '</ul>',
                '</div><div class="section">',
                '<p>Reason: I\'m tring hard to route your url, but I can\'t handle it because:',
                '<ol>',
                '<li>I don\'t find "', $this->data['controller'], '" controller in "',
                $this->data['module'], '" module</li>',
                '<li>I don\'t find "', $this->data['module'], '_wildcard" wildcard url hander in "',
                $this->data['module'], '" module</li>',
                '</ol>',
                '</div><div class="section">',
                '<p>To solve this problem, you can try one of the following steps:</p>',
                '<ul>',
                '<li>Create a "', $this->data['controller'], '" controller in "', $this->data['module'], '"',
                ' module using :</li><li><code>$ php ./script/gen-controller ', $this->data['module'], ' ',
                substr($this->data['controller'], strpos($this->data['controller'], '_') + 1), '</code></li>',
                '<li>Or create a wildcard url hander in "', $this->data['module'], '" module using:</li><li><code>',
                '$ php ./script/gen-controller ', $this->data['module'], ' wildcard</code></li>',
                '</ul></div>';
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
