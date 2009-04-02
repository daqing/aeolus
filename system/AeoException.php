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

            $str = array();
            foreach ($trace as $k => $v) {
                $str[$k] = "#$k {$v['file']}({$v['line']})<p>";
                if (isset($v['class']))
                    $str[$k] .= "{$v['class']}{$v['type']}";

                $str[$k] .= "{$v['function']}(";
                
                $num = count($v['args']);
                if ($num > 0)
                {
                    for ($i = 0; $i < $num - 1; $i ++)
                        $str[$k] .= '"' . $v['args'][$i] . '", ';

                    $str[$k] .= '"' . $v['args'][$num - 1] . '"';
                }
                
                $str[$k] .= ');';
            }

            return $str;
        }
    }

?>
