<?php

    /*
     * Aeolus exception class
     *
     */

    class AeoException extends Exception
    {
        private $level;
        private $handler;

        public function __construct($handler, $level=0)
        {
            $this->level = $level;
            $this->handler = $handler;
        }

        public function handle()
        {
            $action = Aeolus::loadController($this->handler, 'exception');

            $action($this->level);
        }
    }
?>
