<?php

namespace kennysLabs\CommonLibrary;

use kennysLabs\JiraCards\Application;

class Logger {

    /**
     * Error handler, passes flow over the exception logger with new ErrorException.
     * @param $num
     * @param $str
     * @param $file
     * @param $line
     * @param null $context
     */
    public static function logError( $num, $str, $file, $line, $context = null )
    {
        self::logException( new \ErrorException( $str, 0, $num, $file, $line ) );
    }

    /**
     * Uncaught exception handler.
     * @param \Throwable $e
     */
    public static function logException(\Throwable $e)
    {
        if(Application::getInstance()->getConfig()->{'main_section'}['test_mode'] == 'true') {
            if (RUN_ENV == 'www') {
                echo "<div style='text-align: center;'>";
                echo "<h2 style='color: rgb(190, 50, 50);'>Exception Occured:</h2>";
                echo "<table style='width: 800px; display: inline-block;'>";
                echo "<tr style='background-color:rgb(230,230,230);'><th style='width: 80px;'>Type</th><td>" . get_class($e) . "</td></tr>";
                echo "<tr style='background-color:rgb(240,240,240);'><th>Message</th><td>{$e->getMessage()}</td></tr>";
                echo "<tr style='background-color:rgb(230,230,230);'><th>File</th><td>{$e->getFile()}</td></tr>";
                echo "<tr style='background-color:rgb(240,240,240);'><th>Line</th><td>{$e->getLine()}</td></tr>";
                echo "</table></div>";
            } else {
                echo "== Exception Occured  ==" . PHP_EOL;
                echo $e->getMessage() . PHP_EOL;
                echo $e->getFile() . ':' . $e->getLine() . PHP_EOL;
            }
        }

        $message = "Type: " . get_class( $e ) . "; Message: {$e->getMessage()}; File: {$e->getFile()}; Line: {$e->getLine()};";
        file_put_contents( Application::getInstance()->getConfig()->{'main_section'}['log_file'], $message . PHP_EOL, FILE_APPEND );

        exit();
    }

    /**
     * Checks for a fatal error, work around for set_error_handler not working on fatal errors.
     */
    public static function checkForFatal()
    {
        $error = error_get_last();
        if ( $error["type"] == E_ERROR ) {
            self::logError( $error["type"], $error["message"], $error["file"], $error["line"] );
        }
    }

    /**
     * @param array $array
     * @param string $glue
     *
     * @return string
     */
    public static function multiImplode($array, $glue)
    {
        if (!is_array($array)) {
            return $array;
        }

        $ret = '';
        foreach ($array as $item) {
            if (is_array($item)) {
                $ret .= self::multiImplode($item, $glue) . $glue;
            } else {
                $ret .= $item . $glue;
            }
        }
        $ret = substr($ret, 0, 0 - strlen($glue));

        return $ret;
    }
}
