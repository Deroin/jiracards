<?php

namespace kennysLabs\CommonLibrary\ApplicationManager;

abstract class BaseController {

    /** @var  string $templatePath */
    protected $templatePath;

    /** @var  \Twig_Template  $controllerTemplate */
    protected $controllerTemplate;

    /** @var  \Twig_Template  $actionTemplate */
    protected $actionTemplate;

    /** @var  \Twig_Environment $actionTemplate */
    protected $templateEngine;

    /** @var  BaseApplication $applicationInstance */
    private $applicationInstance;

    /** @var bool $showActionTemplate */
    private $showActionTemplate = true;

    /** @var bool $showControllerTemplate */
    private $showControllerTemplate = true;

    /** @var string $stationaryAction */
    private $stationaryAction;

    /** @var array $viewVars */
    private $viewVars = [];

    /**
     * @param BaseApplication $applicationInstance
     * @param Twig_Environment $templateEngine
     */
    public function __construct($applicationInstance, $templateEngine)
    {
        $this->templateEngine = $templateEngine;
        $this->applicationInstance = $applicationInstance;
    }

    /**
     * Render the current request action
     */
    public function render()
    {
        if(isset($this->controllerTemplate))
        {
            if(isset($this->actionTemplate)) {
                return $this->controllerTemplate->render(array_merge(['actionTemplate' => $this->actionTemplate->render($this->viewVars)], $this->viewVars));
            } else {
                return $this->controllerTemplate->render($this->viewVars);
            }
        } else {
            if(isset($this->actionTemplate)) {
                return $this->actionTemplate->render($this->viewVars);
            }
        }
    }

    /**
     * @param string $template
     */
    public function setControllerTemplate($template)
    {
        if($this->showControllerTemplate) {
            $this->controllerTemplate = $this->templateEngine->loadTemplate($template);
        } else {
            unset($this->controllerTemplate);
        }
    }

    /**
     * @param string $template
     */
    public function setActionTemplate($template)
    {
        if($this->showActionTemplate) {
            $this->actionTemplate = $this->templateEngine->loadTemplate($template);
        } else {
            unset($this->actionTemplate);
        }
    }

    /**
     * @param string $key
     * @param mixed $value
     */
    public function setViewVar($key, $value) {
        $this->viewVars[$key] = $value;
    }

    /**
     * @param bool|false $status
     */
    public function toggleActionTemplate($status = false)
    {
        $this->showActionTemplate = $status;
    }

    /**
     * @param bool|false $status
     */
    public function toggleControllerTemplate($status = false)
    {
        $this->showControllerTemplate = $status;
    }

    /**
     *
     */
    public function disableGlobalTemplate() {
        $this->getApplication()->setGlobalTemplate(null);
    }

    /**
     * @param string $action
     */
    public function setStationaryAction($action) {
        $this->stationaryAction = strtolower(trim($action));
    }

    /**
     * @return string
     */
    public function getStationaryAction() {
        return $this->stationaryAction;
    }

    /**
     * @return BaseApplication
     */
    public function getApplication()
    {
        return $this->applicationInstance;
    }

    /**
     * @param string $field
     * @param string $default
     * @return null
     */
    public function getRequestData($field, $default = null) {
        $requestData = $this->getApplication()->getRequestData();
        return isset($requestData[$field]) ? $requestData[$field] : $default;
    }
}