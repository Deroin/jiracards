<?php

namespace kennysLabs\CommonLibrary\ORM;

interface EntityManagerInterface
{
    const TYPE_CREATE_UPDATE = 1;
    const TYPE_CREATE_ONLY = 2;
    const TYPE_UPDATE_ONLY = 3;

    /**
     * @param EntityInterface $entity
     * @param int $persistType
     * @param bool $ignoreChecks
     * @return mixed
     */
    public function persist(EntityInterface $entity, $persistType = self::TYPE_CREATE_UPDATE, $ignoreChecks = false);
}