<?php

namespace kennysLabs\CommonLibrary\ApplicationManager;

use kennysLabs\CommonLibrary\ConfigParser;
use kennysLabs\CommonLibrary\DependencyInjectionManager;
use kennysLabs\CommonLibrary\ApplicationManager\BaseController;

class BaseApplication {
    protected static $instances;
    protected $pdo;
    protected $config;
    protected $requestData;
    protected $route;

    protected $twigLoader;
    protected $twigEngine;
    protected $templatesFolder;

    private $namespace;
    private $di;

    /** @var  Twig_TemplateInterface $globalTemplate */
    private $globalTemplate;

    const DEFAULT_TEMPLATE_FOLDER = 'Resources/views/%controller%/';
    /**
     * @param string $ini
     * @param string $namespace
     * @return BaseApplication
     */
    protected function __construct($ini, $namespace)
    {
        $this->namespace = $namespace;
        $this->di = new DependencyInjectionManager($namespace);

        $this->config = ConfigParser::getInstance($ini);

        if ($this->config->{'db_section'}['db_enabled'] == 'true') {
            $this->pdo = new \FluentPDO(new \PDO("mysql:dbname=" . $this->config->{'db_section'}['db_database'],
                $this->config->{'db_section'}['db_username'],
                $this->config->{'db_section'}['db_password']));
        } else {
            $this->pdo = null;
        }

        // TODO: make paths configurable
        $this->twigLoader = new \Twig_Loader_Filesystem(ROOT_PATH . '/../resources/views/');
        $this->twigEngine= new \Twig_Environment($this->twigLoader, array(
            'cache' => '/tmp',
            'auto_reload' => true //TODO: Production version should be compiled so change..
        ));

        $this->templatesFolder = static::DEFAULT_TEMPLATE_FOLDER;
    }

    /**
     * @return \Twig_Environment
     */
    public function getTemplateEngine()
    {
        return $this->twigEngine;
    }

    /**
     * @return \Twig_Loader_Filesystem
     */
    public function getTemplateLoader()
    {
        return $this->twigLoader;
    }

    /**
     * @param string $ini
     * @param string $namespace
     * @return BaseApplication
     */
    public static function getInstance($ini = '', $namespace = '')
    {
        if (empty($ini))
        {
            $ini = ROOT_PATH . '/../application/config/config.ini';
        }

        if (empty($namespace))
        {
            $namespace = 'kennysLabs';
        }

        if (static::$instances[$ini] instanceof static) {
            return static::$instances[$ini];
        }

        return static::$instances[$ini] = new static($ini, $namespace);
    }

    /**
     * @return mixed
     */
    public function getRequestType() {
        return isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : null;
    }

    /**
     * @return string
     */
    public function getUriRoute()
    {
        $originalUri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';

        if (strpos($originalUri, '?') !== false) {
            return explode('?', $originalUri)[0];
        }

        return $originalUri;
    }

    /**
     * @return array
     */
    public function getRoute()
    {
        return $this->route;
    }

    /*
     * Runs the application
     */
    public function run()
    {
        $this->buildRoute();
        $this->gatherRequestData();

        // check for module specific config
        $baseModulePath = ROOT_PATH . '/../src/' . $this->namespace . '/' . ucfirst(strtolower($this->route['module'])) . 'Module/';
        $ini = $baseModulePath . 'config.ini';

        if(file_exists($ini)) {
            $moduleConfig = ConfigParser::getInstance($ini);
            $this->overwriteRoute(is_array($moduleConfig->{'routes'}) ? $moduleConfig->{'routes'} : []);
            if(!empty($moduleConfig->{'general'}['views']))
            {
                $this->templatesFolder = $moduleConfig->{'general'}['views'];
            }

        }

        $callModule = $this->route['module'] . 'Module';
        $dirModule = sprintf('%s/../src/%s/%s', ROOT_PATH, $this->getRegisteredNamespace(), $callModule);

        if(!is_dir($dirModule)) {
            throw new \Exception('Requested module cannot be found: ' . $callModule);
        }

        $callController = $this->route['controller'] . 'Controller';
        $callAction = $this->route['action'] . 'Action';

        $invokeController = $this->namespace . '\\'. $callModule .'\Controllers\\' . $callController;

        if(!class_exists($invokeController)) {
            throw new \Exception('Requested controller cannot be found: ' . $callController);
        }

        /** @var BaseController $runController */
        $runController = new $invokeController($this, $this->getTemplateEngine());

        if(!empty($runController->getStationaryAction())) {
            $callAction = $runController->getStationaryAction() . 'Action';
        }

        if(!is_callable([$runController, $callAction])) {
            throw new \Exception('Requested action cannot be found: ' . $callAction);
        }

        // Run the action
        $runController->$callAction();

        // Setup the path folder
        $this->getTemplateLoader()->addPath($baseModulePath);

        $runController->setActionTemplate($this->getTemplateForAction());
        $runController->setControllerTemplate($this->getTemplateForController());

        if($this->getGlobalTemplate() instanceof \Twig_Template) {
            echo $this->getGlobalTemplate()->render(['controllerTemplate' => $runController->render()]);

        } else {
            echo $runController->render();
        }

        // TODO: Sanitize all the input
        // TODO: All the checks is actions are callable and controllers / modules exist
        // TODO: Write all the exceptions
    }

    /**
     * @param string $template
     */
    public function setGlobalTemplate($template)
    {
        if(!empty($template)) {
            $this->globalTemplate = $this->twigEngine->loadTemplate($template);
        } else {
            $this->globalTemplate = null;
        }
    }

    /**
     * @return \Twig_TemplateInterface
     */
    public function getGlobalTemplate()
    {
        return $this->globalTemplate;
    }

    /*
     * Gather info from all php variables
     */
    protected function gatherRequestData()
    {
        $this->requestData['post'] = $_POST;
        $this->requestData['get'] = $_GET;
        $this->requestData['files'] = $_FILES;
        $this->requestData['request'] = $_REQUEST;
    }

    /**
     * @param string $route
     * @return array $uri
     */
    protected function resolveRoute($route)
    {
        $prefix = $this->config->{'main_section'}['url_prefix'];
        $route = rtrim($route, '/');
        $route = str_replace($prefix, "", $route);
        $uri = explode('/',
            explode('?', $route)[0]
        );

        array_shift($uri);

        if(empty($uri[count($uri)-1])) {
            unset($uri[count($uri)-1]);
        }

        return $uri;
    }

    /**
     * @return string
     */
    protected function getTemplateForController()
    {
        $dashEnd = '';
        if(substr($this->templatesFolder, -1, 1) == '/') {
            $dashEnd = '/';
        }

        return str_replace('%controller%' . $dashEnd,
            strtolower($this->route['controller']),
            $this->templatesFolder) . '.html.twig';
    }

    /**
     * @return string
     */
    protected function getTemplateForAction()
    {
        $dashEnd = '';
        if(substr($this->templatesFolder, -1, 1) == '/') {
            $dashEnd = '/';
        }

        return str_replace('%controller%' . $dashEnd,
            strtolower($this->route['controller']),
            $this->templatesFolder) . $dashEnd . strtolower($this->route['action']) . '.html.twig';
    }

    /**
     * Build predefined route based on the input parameters
     * @return $this
     */
    protected function buildRoute()
    {
        // TODO: handle all kind of possible problems / exceptions

        $requestUrl = $this->resolveRoute($_SERVER['REQUEST_URI']);

        $pattenUrl = $this->resolveRoute($this->config->{'main_section'}['url_pattern']);

        $moduleOffset = !empty($this->config->{'main_section'}['url_default_module']);

        $replacements = [0 => $moduleOffset ?
                ucFirst($this->config->{'main_section'}['url_default_module']) :
                (isset($requestUrl[0]) ?
                    ucFirst($requestUrl[0]) :
                    'Main'),
            1 => isset($requestUrl[(1-$moduleOffset)]) ?
                    ucFirst($requestUrl[(1-$moduleOffset)]) :
                    'Index',
            2 => isset($requestUrl[(2-$moduleOffset)]) ?
                    ucFirst($requestUrl[(2-$moduleOffset)]) :
                    'Index'
        ];

        for ($i = 3; $i < count($requestUrl); $i++)
        {
            $replacements[3][] = $requestUrl[($i-$moduleOffset)];
        }

        $this->route = [];
        foreach ($pattenUrl as $key => $value)
        {
            $sanitized = filter_var(isset($replacements[$key]) ? $replacements[$key] : '', FILTER_SANITIZE_URL);
            for($i = 0; $i < strlen($sanitized); $i++) {
                if($sanitized{$i} == '-') {
                    // remove '-'
                    $sanitized = substr_replace($sanitized, '', $i, 1);
                    // replace with camel case
                    $sanitized = substr_replace($sanitized, strtoupper($sanitized[$i]), $i, 1);
                }
            }

            $this->route[substr($value, 1, -1)] = $sanitized;
        }

        return $this;
    }

    /**
     * @param array $newRoutes
     * @return $this
     */
    protected function overwriteRoute($newRoutes)
    {
        foreach ($newRoutes as $idx => $route)
        {
            $splitOffset = 0;
            $splitRoute = explode('/', $idx);
            $splitForward = explode(':', $route);

            if(count($splitForward) == 3) {
                $this->route['module'] = ucfirst(strtolower($splitForward[0]));
                $splitOffset = 1;
            }

            if(isset($splitRoute[0]) && $this->route['controller'] == ucfirst(strtolower($splitRoute[0]))) {
                $this->route['controller'] = ucfirst(strtolower($splitForward[$splitOffset]));
            }
            if(isset($splitRoute[1]) && $this->route['action'] == ucfirst(strtolower($splitRoute[1]))) {
                $this->route['action'] = ucfirst(strtolower($splitForward[1+$splitOffset]));
            }
        }

        return $this;
    }

    /*
     * @param string $index
     * @return array
     */
    public function getRequestData($index = 'request') {
        return !empty($this->requestData[$index]) ? $this->requestData[$index] : [];
    }

    /**
     * @return string
     */
    public function getRegisteredNamespace()
    {
        return $this->namespace;
    }

    /**
     * @return DependencyInjectionManager $namespace
     */
    public function getDi()
    {
        return $this->di;
    }

    /**
     * @return ConfigParser
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @return \FluentPDO
     */
    public function getPdo()
    {
        return $this->pdo;
    }
}

