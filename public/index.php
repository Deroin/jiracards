<?php

require_once(__DIR__ . '/../application/bootstrap.php');

define('RUN_ENV', 'www');

use kennysLabs\JiraCards\Application;

Application::getInstance()->run();
