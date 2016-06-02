<?php

namespace kennysLabs\CommonLibrary;

class ConfigParser
{
    const AUTO = 0;
    const JSON = 2;
    const PHP_INI = 4;
    const XML = 16;

    /** @var array */
    static private $CONF_EXT_RELATION = array(
        'json' => 2, // JSON
        'ini' => 4,  // PHP_INI
        'xml' => 16  // XML
    );


    private static $instance;

    /** @var mixed $data */
    private $data;

    /**
     * @param $file
     * @param int $type
     */
    private function __construct($file, $type = ConfigParser::AUTO) {
        if ($type == self::AUTO) {
            $type = self::$CONF_EXT_RELATION[pathinfo($file, PATHINFO_EXTENSION)];
        }

        switch($type) {
            case self::JSON:
                $this->data = json_decode(file_get_contents($file), true);
                break;

            case self::PHP_INI:
                $this->data = parse_ini_file($file, true);
                break;

            case self::XML:
                $this->data = $this->objectToArray(simplexml_load_file($file));
                break;
        }
    }

    /**
     * @param string $file
     * @param int $type
     * @return ConfigParser
     */
    public static function getInstance($file, $type = ConfigParser::AUTO) {
        if( !isset(self::$instance[$file])) {
            self::$instance[$file] = new self($file, $type);
        }

        return self::$instance[$file];
    }

    /**
     * @param string $section
     * @return object mixed
     */
    public function __get($section) {
        if ((is_array($this->data)) &&
            (array_key_exists($section, $this->data))) {
            return $this->data[$section];
        }
    }

    /**
     * @return array
     */
    public function getAvailableSections() {
        return array_keys($this->data);
    }

    /**
     * @param $obj
     * @return array
     */
    private function objectToArray($obj) {
        $arr = (is_object($obj))?
            get_object_vars($obj) :
            $obj;

        foreach ($arr as $key => $val) {
            $arr[$key] = ((is_array($val)) || (is_object($val)))?
                $this->objectToArray($val) :
                $val;
        }

        return $arr;
    }
}
