<?php

/* Aeolus environment */
class AeoEnv
{
    private $data;

    public function __construct($initEnv=null)
    {
        if (is_array($initEnv)) {
            $this->data = $initEnv;
        } else {
            $log = AeoSystem::getLogger();
            $log->warn('AeoEnv was initialized with wrong data: ' . $initEnv);
        }
    }

    public function set($key, $value)
    {
        $this->data[$key] = $value;
    }

    public function get($key)
    {
        if (isset($this->data[$key])) {
            return $this->data[$key];
        } else {
            return null;
        }
    }
}

?>
