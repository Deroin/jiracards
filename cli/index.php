<?php

require_once(__DIR__ . '/../application/bootstrap.php');

define('RUN_ENV', 'cli');

use kennysLabs\JiraCards\Application;

// Defaults
$module = 'cli';
$controller = 'index';
$action = 'index';

foreach($argv as $argument) {
    if (strpos($argument, '--module=') !== false) {
        $module = explode('=', $argument)[1];
    }

    if (strpos($argument, '--controller=') !== false) {
        $controller = explode('=', $argument)[1];
    }

    if (strpos($argument, '--action') !== false) {
        $action = explode('=', $argument)[1];
    }

}

$_SERVER['REQUEST_URI'] = sprintf('/%s/%s/%s', $module, $controller, $action);

Application::getInstance()->run();
