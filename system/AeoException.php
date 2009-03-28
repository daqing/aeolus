<?php

    /*
     * Aeolus exception class
     *
     */

    class AeoException extends Exception
    {
        private $argv = array();

        /**
         * Constructor
         *
         * @param array $argv array('name' => (string) $name, 'detail' => (string) $detail, 'runtime' => (array) $runtime)
         */
        public function __construct($argv)
        {
            $this->argv = $argv;

            $this->format_runtime();
        }

        public function __toString()
        {
            return $this->argv['name'] . 'Exception';
        }

        public function show($is_debug)
        {
            if ($is_debug) {
                $v = Aeolus::newView('ExceptionDebug', 'index');
                $v->title = 'ExceptionCaught';
            } else {
                $v = Aeolus::newView('ExceptionNormal', 'index');
                $v->title = 'SomethingWrong';
            }

            $v->data = $this->get_data();
            $v->data['trace'] = $this->get_trace();

            $v->show();
        }

        private function format_runtime()
        {
            $str = 'Array(';

            foreach ($this->argv['runtime'] as $k => $v)
                $str .= "'$k' => '$v', ";

            $this->argv['runtime'] = $str . ');';
        }

        public function get_data()
        {
            return $this->argv;
        }

        public function get_trace()
        {
            $trace = parent::getTrace();

            foreach ($trace as $k => $v) {
                $str[$k] = "#$k {$v['file']}({$v['line']})<p>{$v['class']}{$v['type']}{$v['function']}(";

                if ($v['args']) {
                    $str[$k] .= "array(". implode(', ', $v['args']) . ")";
                }

                $str[$k] .= ')';
            }

            return $str;
        }
    }

?>
