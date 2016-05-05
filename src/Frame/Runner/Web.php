<?php
namespace Ice\Frame\Runner;
class Web {
    protected $rootPath;

    // input data
    public $serverEnv;
    public $clientEnv;
    public $request;

    // static info
    public $mainAppConf;

    // output data
    public $response;

    public function __construct($rootPath) {
        $this->rootPath = $rootPath;
        $this->mainAppConf = \F_Config::getConfig($this->rootPath . '/conf/app.php');
    }

    public function run() {
        $this->initIce();

        $this->setupEnv();
        $this->setupRequest();
        $this->setupResponse();

        $this->setupIce($this);

        $this->route();

        $this->dispatch();
    }

    protected function setupEnv() {
        $serverEnvClass = isset($this->mainAppConf['frame']['server_env_class'])
                        ? $this->mainAppConf['frame']['server_env_class']
                        : '\\Ice\\Frame\\Web\\ServerEnv';
        $clientEnvClass = isset($this->mainAppConf['frame']['client_env_class'])
                        ? $this->mainAppConf['frame']['client_env_class']
                        : '\\Ice\\Frame\\Web\\ClientEnv';

        $this->serverEnv  = new $serverEnvClass();
        $this->clientEnv  = new $clientEnvClass();
    }

    protected function setupRequest() {
        $requestClass = isset($this->mainAppConf['frame']['request_class'])
                        ? $this->mainAppConf['frame']['request_class']
                        : '\\Ice\\Frame\\Web\\Request';
        $this->request    = new $requestClass();
    }

    protected function setupResponse() {
        $responseClass = isset($this->mainAppConf['frame']['response_class'])
                        ? $this->mainAppConf['frame']['response_class']
                        : '\\Ice\\Frame\\Web\\Response';
        $this->response = new $responseClass();
    }

    protected function initIce() {
        $this->ice = \F_Ice::init($this, $this->rootPath);
    }

    protected function setupIce() {
        $this->ice->setup();
    }

    protected function __cmpPatternType($p1, $p2) {
        static $patternPriorities = array(
            '==' => 1,
            'i=' => 2,
            '^=' => 3,
            'i^' => 4,
            '$=' => 5,
            'i$' => 6,
            '~=' => 7,
        );
        $t1 = substr(trim($p1), 0, 2);
        $t2 = substr(trim($p2), 0, 2);
        return $patternPriorities[$t1] - $patternPriorities[$t2];
    }

    /**
     * route 
         优先处理特殊路由. 优先级自上而下:
         1. "==": 精确匹配
         2. "i=": 不区分大小写精确匹配
         3. "^=": 精确前缀匹配
         4. "i^": 不区分大小写前缀匹配
         5. "$=": 精确后缀匹配
         6. "i$": 不区分大小写后缀匹配
         7. "~=": 正则匹配
         8. 自定义路由: 逗号分隔, 直到一个路由器返回TRUE
     * @access protected
     * @return void
     */
    protected function route() {
        $routes = $this->mainAppConf['routes'];
        $defaultRouteClasses = $routes['default'];
        unset($routes['default']);

        // sort with priority
        uksort($routes, array($this, '__cmpPatternType'));

        $routed = FALSE;

        foreach ($routes as $pattern => $rule) {
            $type    = substr(trim($pattern), 0, 2);
            $pattern = trim(substr(trim($pattern), 2));
            $isMatch = FALSE;
            $params  = array();
            switch ($type) {
                case '==':
                    $isMatch = strcmp($pattern, $this->request->uri) == 0;
                    break;
                case 'i=':
                    $isMatch = strcasecmp($pattern, $this->request->uri) == 0;
                    break;
                case '^=':
                    $isMatch = strncmp($pattern, $this->request->uri, strlen($pattern)) == 0;
                    break;
                case 'i^':
                    $isMatch = strncasecmp($pattern, $this->request->uri, strlen($pattern)) == 0;
                    break;
                case '$=':
                    $offset  = max(0, strlen($this->request->uri) - strlen($pattern));
                    $isMatch = strcmp($pattern, substr($this->request->uri, $offset)) == 0;
                    break;
                case 'i$':
                    $offset  = max(0, strlen($this->request->uri) - strlen($pattern));
                    $isMatch = strcasecmp($pattern, substr($this->request->uri, $offset)) == 0;
                    break;
                case '~=':
                    $isMatch = preg_match($pattern, $this->request->uri, $params);
                    break;
                default:
                    break;
            }

            if ($isMatch) {
                $this->request->controller  = $rule['controller'];
                $this->request->action      = $rule['action'];
                $this->response->controller = $rule['controller'];
                $this->response->action     = $rule['action'];
                if (isset($rule['params']) && !empty($params)) {
                    foreach ($rule['params'] as $matchKey => $paramName) {
                        $this->request->setParam($paramName, $params[$matchKey]);
                    }
                }
                return ;
            }
        }

        $defaultRouteClasses = explode(',', $defaultRouteClasses);
        foreach ($defaultRouteClasses as $routeClass) {
            $router = new $routeClass();
            if ($router->route($this->request, $this->response)) {
                return ;
            }
        }
    }

    protected function dispatch() {
        try {
            $className = "\\{$this->mainAppConf['namespace']}\\Action\\{$this->request->controller}\\{$this->request->action}";

            if (!class_exists($className) || !method_exists($className, 'execute')) {
                \F_Ice::$ins->mainApp->logger_common->fatal(array(
                    'controller' => $this->request->controller,
                    'action' => $this->request->action,
                    'msg' => 'dispatch error: no class or method',
                ));
                return $this->response->error(\F_ECode::UNKNOWN_URI, array(
                    'controller' => $this->request->controller,
                    'action' => $this->request->action,
                    'msg' => 'dispatch error: no class or method',
                ));
            }

            $actionObj = new $className();
            $actionObj->setRequest($this->request);
            $actionObj->setResponse($this->response);
            $actionObj->setServerEnv($this->serverEnv);
            $actionObj->setClientEnv($this->clientEnv);

            $actionObj->prevExecute();
            $tplData = $actionObj->execute(); 
            $actionObj->postExecute();

            $this->response->setTplData($tplData);
            $this->response->output();
        } catch (\Exception $e) {
            \F_Ice::$ins->mainApp->logger_common->fatal(array(
                'exception' => get_class($e),
                'message'   => $e->getMessage(),
                'code'      => $e->getCode(),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
            ));
            $this->response->error(\F_ECode::PHP_ERROR);
        }
    }
}