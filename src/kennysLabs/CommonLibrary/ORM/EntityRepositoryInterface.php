<?php

namespace kennysLabs\CommonLibrary\ORM;

interface EntityRepositoryInterface
{
    public function __construct(\FluentPDO $pdo);

    /**
     * @param array $criteria
     * @return array
     */
    public function fetchAll($criteria = []);
}