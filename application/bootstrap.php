<?php

define('ROOT_PATH', dirname(__FILE__) );

try {
    require_once __DIR__ . '/../vendor/autoload.php';
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
}

register_shutdown_function( "kennysLabs\CommonLibrary\Logger::checkForFatal" );
set_error_handler( "kennysLabs\CommonLibrary\Logger::logError" );
set_exception_handler("kennysLabs\CommonLibrary\Logger::logException" );

error_reporting( E_ALL );
ini_set('display_errors', 1);