<?php

namespace kennysLabs\CommonLibrary;

class DependencyInjectionManager
{
    /** @var  string $namespace */
    private $namespace;

    /** @var  array $repository */
    private $repository;

    public function __construct($namespace)
    {
        $this->namespace = $namespace;
    }

    /**
     * @param string $objectName
     * @param mixed $object
     */
    public function add($objectName, $object)
    {
        $this->repository[$this->namespace][$objectName] = $object;
    }

    /**
     * @param $objectName
     * @return mixed
     * @throws \Exception
     */
    public function get($objectName)
    {
        if(!isset($this->repository[$this->namespace]) || !isset($this->repository[$this->namespace][$objectName])) {
            throw new \Exception('Requested object (' . $objectName . ') from ' . $this->namespace . ' namespace is not set');
        }
        return $this->repository[$this->namespace][$objectName];
    }

    /**
     * @param string $objectName
     * @throws \Exception
     */
    public function remove($objectName)
    {
        if(!isset($this->repository[$this->namespace]) || !isset($this->repository[$this->namespace][$objectName])) {
            throw new \Exception('Requested object (' . $objectName . ') from ' . $this->namespace . ' namespace can\'t be deleted');
        }
        unset($this->repository[$this->namespace][$objectName]);
    }
}