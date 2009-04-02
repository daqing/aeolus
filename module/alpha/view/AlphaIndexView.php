<?php

    /* AlphaIndexView class in alpha module */

    class AlphaIndexView extends AeoView
    {
        public function show_frame()
        {
            ?>
            <div class="window">
                <h3 class="title">title</h3>
                <div class="tabs">
                    <ul>
                        <li><a href="#foo">foo</a></li>
                        <li><a href="#bar">bar</a></li>
                    </ul>
                    <div id="foo">
                        this is foo tab
                    </div>
                    <div id="bar">
                        this is bar tab
                    </div>
                </div>
            </div>
            <?php
        }

        public function show_style()
        {
            ?>
            <style type="text/css">
            .window { background-color: #F8F8FF; border: 4px solid #DEDEDE; width: 95%; height: 80%; min-height: 300px;}
            .window h3 { text-align: center; font-weight: 0.9em; }
            .window .status { background-color: #red; }
            </style>
            <?php
        }

        public function show_script()
        {
            parent::show_script();
            ?>
            <script type="text/javascript">
            $(function() {
                $(".window").draggable().resizable();
                $(".tabs").tabs();
            });
            </script>
            <?php
        }
    }
?>
