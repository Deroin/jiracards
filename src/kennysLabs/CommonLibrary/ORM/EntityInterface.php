<?php

namespace kennysLabs\CommonLibrary\ORM;

interface EntityInterface
{
    /**
     * @return array
     */
    public function getPrimaryKey();

    /**
     * @return array
     */
    public function getUniqueKeys();

    /**
     * @return array
     */
    public function getEntityFields();

    /**
     * @return array
     */
    public function getSetKeys();

    /**
     * @param string $fieldName
     * @return mixed
     * @throws \Exception
     */
    public function get($fieldName);

}