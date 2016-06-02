<?php

namespace kennysLabs\CommonLibrary\ORM;

class EntityAbstract implements EntityInterface
{
    const IGNORE_SET_KEYS = 'ignore_set_keys';
    const ENTITY_NAME = '';

    /** @var [] $_fields */
    protected $_fields;

    /** @var [] $_primaryKey */
    protected $_primaryKey;

    /** @var [] $_uniqueKeys */
    protected $_uniqueKeys;

    /** @var array $_setKeys */
    protected $_setKeys;

    /**
     * @return array
     */
    public function getPrimaryKey()
    {
        return $this->_primaryKey;
    }

    /**
     * @return array
     */
    public function getEntityFields()
    {
        return $this->_fields;
    }

    /**
     * @return array
     */
    public function getSetKeys()
    {
        return $this->_setKeys;
    }

    /**
     * @return array
     */
    public function getUniqueKeys()
    {
        return $this->_uniqueKeys;
    }

    /**
     * @return string
     */
    public function getTableName()
    {
        return static::ENTITY_NAME;
    }

    /**
     * @param string $fieldName
     * @return mixed
     * @throws \Exception
     */
    public function get($fieldName)
    {
        $caller = 'get' . implode('', array_map(function($word) { return ucfirst($word); }, explode('_', $fieldName)));

        if(is_callable(array($this, $caller))) {
            return $this->$caller();
        } else
        {
            throw new \Exception('Field ' . $fieldName . ' doesn\'t exist in ' . self::class . ' entity');
        }
    }

    /**
     * @param array $data
     * @return EntityInterface
     * @throws \Exception
     */
    public static function createEntityFromArray(array $data)
    {
        $entity = new static();

        if(is_array(reset($data)) && count($data) > 1)
        {
            throw new \Exception('Multiple rows of data. Use createEntityCollectionFromArray method.');
        }
        else if (is_array(reset($data)))
        {
            $data = array_pop($data);
        }

        foreach ($data as $idx => $value) {
            $method = 'set' . implode('', array_map(function($word) { return ucfirst($word); }, explode('_', $idx)));

            $check = array($entity, $method);
            if(is_callable($check)) {
                $entity->$method($value, self::IGNORE_SET_KEYS);
            } else {
                throw new \Exception('Entity and provided array are incompatible.');
            }
        }

        return $entity;
    }

    /**
     * @param array $data
     * @return EntityInterface
     */

    public static function createEntityCollectionFromArray(array $data)
    {
        // TODO: Not yet implemented.. needs collections
    }

    /**
     * @param string $method
     * @param mixed $args
     * @return mixed
     * @description Pre-hook for set*() methods to track changed fields
     */
    public function __call($method, $args)
    {
        $propertyName = strtolower(substr(preg_replace('/([A-Z])/', '_$1', $method), 4, strlen($method)));

        if (in_array($propertyName, $this->_fields) && substr($method, 0, 3) == 'set')
        {
            if(!isset($args[1]) || $args[1] != self::IGNORE_SET_KEYS) {
                $this->_setKeys[] = $propertyName;
            }

            if(method_exists($this, '_' . $method)) {
                return call_user_func_array(array($this, '_' . $method), $args);
            }
        }
        else
        {
            if(method_exists($this, $method)) {
                return call_user_func_array(array($this, $method), $args);
            }
        }
        return $this;
    }
}