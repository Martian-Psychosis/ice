<?php
namespace Ice\Frame;
class Ice {
    // static info
    public $rootPath;

    // context
    public $mainApp;
    public $workApp;
    public $runner;

    // handler
    public $errorHandler;

    public static $ins;

    protected function __construct($runner, $rootPath) {
        $this->runner   = $runner;
        $this->rootPath = $rootPath;
    }

    public static function init($runner, $rootPath) {
        self::$ins = new self($runner, $rootPath);
        return self::$ins;
    }

    public function setup() {
        // setup application
        // no class exists check. you must guarantee that by yourself pre online
        $mainAppNamespace = $this->runner->mainAppConf['app_class'];
        $mainAppClass     = $this->runner->mainAppConf['app_class'];
        $this->mainApp    = new $mainAppClass($this->rootPath);
        $this->workApp    = $this->mainApp;
        \F_App::registerApp($mainAppNamespace, $this->mainApp);

        // setup errorhandler
        $this->errorHandler = new \Ice\Frame\Error\Handler();
    }
}