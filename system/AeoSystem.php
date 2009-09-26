<?php

AeoLib::depends(array('AeoEnv', 'AeoLog'));

/* System info */
class AeoSystem
{
    public $env;
    public $debug = true;
    public $isRunning = true;
    public $defaultModule = 'index';

    public $homePath;
    public $webRoot;
    public $indexURL;
    public $validControllers;

    private static $log;

    public function __construct()
    {
        $this->homePath = dirname(dirname(__FILE__)) . '/';
        $this->webRoot = '/aeolus/';
        $this->indexURL = $this->webRoot . 'index.php/';

        $this->validControllers = array('index', 'account', 'alpha');

        $this->env = new AeoEnv();
    }

    public function boot()
    {
        $this->debug ? error_reporting(E_ALL) : error_reporting(0);

        if (! $this->isRunning) {
            $page = new AeoPage('SystemNotRunning');

            $page->show();
        }

        # init environment
        $this->env->set('module', 'index');
        $this->env->set('controller', 'index');

        AeoLib::depends('AeoFront');

        $front = new AeoFront($this);

        $url = isset($_GET['url']) ? $_GET['url'] : $_SERVER['REQUEST_URI'];

        $front->route($url);
    }

    # show error message
    public function quit($msg)
    {
        die('error: ' . $msg);
    }

    public static function getLogger()
    {
        if (self::$log == null) {
            self::$log = new AeoLog();
        }

        return self::$log;
    }
}

?>
