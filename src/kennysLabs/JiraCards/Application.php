<?php
namespace kennysLabs\JiraCards;

use kennysLabs\CommonLibrary\ApplicationManager\BaseApplication;
use kennysLabs\CommonLibrary\ORM\EntityManagerPDO;
use kennysLabs\CommonLibrary\ORM\EntityGenerator;


class Application extends BaseApplication
{
    protected static $instance;

    private $entityManagerPDO;
    private $userEntityRepository;
    private $userEventSubscriber;
    private $simpleEventBus;
    private $roleRepository;
    private $userFactory;
    private $userRepository;
    private $unitOfWork;
    private $userService;

    /**
     * @inheritdoc
     */
    protected function __construct($ini = '', $namespace = '')
    {
        parent::__construct($ini, $namespace);

        $this->setGlobalTemplate('base.html.twig');
    }
}