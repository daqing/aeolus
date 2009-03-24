<?php

    /*
     * Aeolus exception class
     *
     */

    class AeoException extends Exception
    {
        private $argv;
        private $handler;

        public function __construct($handler, $argv=0)
        {
            $this->argv = $argv;
            $this->handler = $handler;
        }

        public function handle()
        {
            $action = Aeolus::loadController($this->handler, 'exception');

            $action($this->argv);
        }
    }
?>
