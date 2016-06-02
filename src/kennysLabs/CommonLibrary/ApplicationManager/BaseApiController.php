<?php

namespace kennysLabs\CommonLibrary\ApplicationManager;

use kennysLabs\JiraCards\Application\ApiModule\Acl\ApiAccessControl;
use kennysLabs\JiraCards\Domain\Api\Model\PermissionsRequest;
use kennysLabs\JiraCards\Domain\Api\ApiModuleInterface;

abstract class BaseApiController extends BaseController {

    /** @var array $classMap */
    private $classMapper = [];

    /** @var ApiAccessControl $authenticator */
    private $authenticator;

    /**
     * @param BaseApplication $applicationInstance
     * @param Twig_Environment $templateEngine
     */
    public function __construct($applicationInstance, $templateEngine)
    {
        parent::__construct($applicationInstance, $templateEngine);

        // Disable views
        $this->toggleActionTemplate(false);
        $this->toggleControllerTemplate(false);
        $this->disableGlobalTemplate();
    }

    /**
     * @param ApiModuleInterface $module
     * @param sting $path
     * @throws \Exception
     */
    public function registerApiModule(ApiModuleInterface $module, $path) {
        $this->classMapper[$path] = $module;
    }

    /**
     * @param ApiAccessControl $authenticator
     */
    public function registerAuthenticationClass(ApiAccessControl $authenticator) {
        $this->authenticator = $authenticator;
    }

    /**
     * Handles the API request
     */
    public function handle() {
        $this->sendPreHeaders();

        $success = false;
        $currentAccessLevel = null;

        try {
            foreach($this->classMapper as $idx => $class) {
                if ($idx == $this->getApplication()->getUriRoute()) {
                    $restAction = strtolower($this->getApplication()->getRequestType());
                    if(method_exists($class, $restAction)) {
                        if($this->authenticator) {
                            if(!$this->authenticator->validate($this->getRequiredPermissions($class, $restAction), $currentAccessLevel)) {
                                $this->handleErroneousResponse('Access denied');
                            }
                        }

                        $success = true;
                        $this->handleSuccessfulResponse($class->$restAction($this->getApplication()->getRequestData(), $currentAccessLevel), $class->getErrors());
                    }
                } else if (strpos($this->getApplication()->getUriRoute(), $idx) !== false) {
                    $nonRestAction = str_replace('/', '', str_replace($idx, '', $this->getApplication()->getUriRoute()));

                    if(method_exists($class, $nonRestAction)) {
                        if($this->authenticator) {
                            if(!$this->authenticator->validate($this->getRequiredPermissions($class, $nonRestAction), $currentAccessLevel)) {
                                $this->handleErroneousResponse('Access denied');
                            }
                        }

                        $success = true;
                        $this->handleSuccessfulResponse($class->$nonRestAction($this->getApplication()->getRequestData(), $currentAccessLevel), $class->getErrors());
                    }
                }
            }
        } catch (\Exception $e) {
            $this->handleErroneousResponse($e->getMessage());
        }

        if(!$success) {
            $this->handleErroneousResponse('No route for this request: ' . $this->getApplication()->getUriRoute() . '. Missing some slash?');
        }
    }

    /**
     * Send appropriate headers
     */
    private function sendPreHeaders() {
        $allowedApiClientHosts = $this->getApplication()->getConfig()->{'api_section'}['allowed_hosts'];

        header('Access-Control-Allow-Origin: ' . $allowedApiClientHosts);
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 1000');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

        // Access-Control headers are received during OPTIONS requests
        if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {

            header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");

            exit(0);
        }
    }

    /**
     * @param mixed $data
     * @param array $errors
     */
    private function handleSuccessfulResponse($data, $errors = []) {
        header("HTTP/1.1 200 OK");
        header('Content-Type: application/json');
        echo json_encode(empty($errors) ? $this->successfulResponse($data) : $this->failedResponse($errors));
        exit;
    }

    /**
     * @param mixed $data
     */
    private function handleErroneousResponse($data) {
        header('HTTP/1.1 500 Internal Server Error');
        header('Content-Type: application/json');
        echo json_encode($this->failedResponse($data));
        exit;
    }

    /**
     * @param string $class
     * @param string $method
     * @return array
     */
    private function getClassAnnotations($class, $method)
    {
        $r = new \ReflectionMethod($class, $method);

        $doc = $r->getDocComment();
        preg_match_all('#@(.*?)\n#s', $doc, $annotations);
        return $annotations[1];
    }

    /**
     * @param string $class
     * @param string $method
     * @return PermissionsRequest
     */
    private function getRequiredPermissions($class, $method) {
        $annotations = $this->getClassAnnotations($class, $method);
        $accessInfo = ['access' => null, 'permission' => null, 'level' => null];

        foreach($annotations as $annotation) {
            $aInfo = explode(' ', $annotation);
            $accessInfo[trim($aInfo[0])] = trim($aInfo[1]);
        }

        if($accessInfo['access'] == 'protected') {
            return new PermissionsRequest($accessInfo['permission'], $accessInfo['level']);
        }

        return null;
    }


    /**
     * @param mixed $data
     * @return array
     */
    public function failedResponse($data = null) {
        $retval = ['result' => 'error'];

        if (!empty($data)) {
            $retval = array_merge($retval,  ['message' => $data]);
        }

        return $retval;
    }

    /**
     * @param mixed $data
     * @return array
     */
    public function successfulResponse($data = null) {
        $retval = ['result' => 'success'];

        if (!empty($data)) {
            $retval = array_merge($retval, ['message' => $data]);
        }

        return $retval;
    }
}