<?php

    /*
     * Aeolus exception class
     *
     */

    class AeoException extends Exception
    {
        public $argv;
        public $handler;

        public function __construct($handler, $argv=0)
        {
            $this->argv = $argv;
            $this->handler = $handler;
        }

        public function toString()
        {
            return '<p><h2>An exception occurred:</h2>'.
                '<ul>'.
                '<li>hander: \''. $this->handler. '\'</li>'.
                '<li>argv: '. $this->format_argv() . '</li>'.
                '</ul></p>';
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
