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
            try {
                $action = Aeolus::loadController($this->handler, 'exception');

                $action($this->argv);
            } catch (AeoException $e) {
                echo $e->toString();
            }
        }

        public function toString()
        {
            return 'An exception occurred:'.
                '<ul>'.
                '<li>hander: \''. $this->handler. '\'</li>'.
                '<li>argv: '. $this->format_argv() . '</li>'.
                '</ul>';
        }

        private function format_argv()
        {
            $str = 'Array(';

            foreach ($this->argv as $k => $v)
                $str .= "'$k' => '$v', ";

            return $str . ');';
        }
    }
?>
