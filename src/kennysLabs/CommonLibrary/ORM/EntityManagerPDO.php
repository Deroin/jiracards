<?php

namespace kennysLabs\CommonLibrary\ORM;

class EntityManagerPDO implements EntityManagerInterface
{
    private $pdo;

    /**
     * @param \FluentPDO $pdo
     */
    public function __construct(\FluentPDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @inheritdoc
     */
    public function persist(EntityInterface $entity, $persistType = self::TYPE_CREATE_UPDATE, $disableChecks = false)
    {
        if($disableChecks && $persistType == self::TYPE_CREATE_UPDATE)
        {
            throw new \Exception('Can\'t have persistence of CREATE/UPDATE type with checks disabled.');
        }

        $newCheck = 0;
        $primaryKeyValues = [];
        $uniqueKeyValues = [];
        $primaryKey = $entity->getPrimaryKey();
        $uniqueKeys = $entity->getUniqueKeys();

        // Get primary key values
        foreach($primaryKey as $key)
        {
            $method = 'get' . implode('', array_map(function($word) { return ucfirst($word); }, explode('_', $key)));
            $check = array($entity, $method);

            if(is_callable($check))
            {
                $primaryKeyValues[$key] = $entity->$method();
                $newCheck += (int)empty($primaryKeyValues[$key]);
            } else
            {
                throw new \Exception('Faulty entity passed. Missing method getter');
            }
        }

        // Now get unique values
        foreach($uniqueKeys as $key)
        {
            $method = 'get' . ucfirst($key);
            $check = array($entity, $method);
            if(is_callable($check))
            {
                $uniqueKeyValues[$key] = $entity->$method();
            } else
            {
                throw new \Exception('Faulty entity passed. Missing method getter');
            }
        }


        // If checks are off.. just try to persist trusting the caller.
        if($disableChecks)
        {
            if ($persistType == self::TYPE_CREATE_ONLY)
            {
                return $this->createNew($entity);
            }
            else if ($persistType == self::TYPE_UPDATE_ONLY)
            {
                return $this->updateExisting($primaryKeyValues, $entity);
            }

            return false;
        }


        // Let's check for duplicates:
        if ($persistType == self::TYPE_CREATE_ONLY) {
            $query = $this->pdo->from($entity->getTableName());

            foreach($uniqueKeyValues as $idx => $pkv) {
                if(is_null($pkv)) {
                    $query = $query->where($idx . ' IS NULL');
                } else {
                    $query = $query->where($idx . '= ?', $pkv);
                }
            }

            if(count($duplicate = $query->fetchAll()) > 0 && !empty($uniqueKeyValues)) {
                throw new \Exception('Duplicate found for: ' . implode(', ', array_values($uniqueKeyValues)));
            }
        }

        // Ok, no duplicates so far... let's check for the primary keys

        // This means that the primary key wasn't specified and theoretically should be auto incremental
        if ($newCheck == count($primaryKey)) {
            if ($persistType == self::TYPE_UPDATE_ONLY)
            {
                throw new \Exception('Update only type specified. Create requested.');
            }
            return $this->createNew($entity);
        } elseif (count($primaryKey) > 0) {
            // rimary key was passed in the entity and we should check if it exists already in the DB.
            $query = $this->pdo->from($entity->getTableName());

            foreach($primaryKeyValues as $idx => $pkv) {
                if(is_null($pkv)) {
                    $query = $query->where($idx . ' IS NULL');
                } else {
                    $query = $query->where($idx . '= ?', $pkv);
                }
            }

            if(count($query->fetchAll()) > 0) {
                if ($persistType == self::TYPE_CREATE_ONLY)
                {
                    throw new \Exception('Create only type specified. Update requested.');
                }
                return $this->updateExisting($primaryKeyValues, $entity);
            } else {
                if ($persistType == self::TYPE_UPDATE_ONLY)
                {
                    throw new \Exception('Update only type specified. Create requested.');
                }
                return $this->createNew($entity);
            }
        }
    }

    /**
     * @param EntityInterface $entity
     * @return boolean
     * @throws \PDOException
     */
    private function createNew(EntityInterface $entity)
    {
        $query = 'INSERT INTO `' . $entity->getTableName() . '` ';
        $query .= '(';
        foreach ($entity->getEntityFields() as $field)
        {
            $fieldValue = $entity->get($field);
            if(in_array($field, $entity->getPrimaryKey()) && $fieldValue == null) {
                continue;
            }

            $query .= '`' . $field . '`, ';
        }
        $query = substr($query, 0, -2);
        $query .= ') VALUES (';

        foreach ($entity->getEntityFields() as $field)
        {
            $fieldValue = $entity->get($field);
            $fieldType = gettype($fieldValue);

            if(in_array($field, $entity->getPrimaryKey()) && $fieldValue == null) {
                continue;
            }

            if($fieldType == 'boolean') {
                $fieldValue = $fieldValue ? '1' : '0';
            }

            if(($field == 'created_at' || $field == 'updated_at') && is_null($fieldValue)) {
                $fieldValue = date('Y-m-d H:i:s', time());
            }

            $value = is_null($fieldValue) ? 'NULL, ' : '\'' . $fieldValue  . '\', ';
            $query .= $value;
        }

        $query = substr($query, 0, -2);
        $query .= ');';

        $query = $this->pdo->getPdo()->prepare($query);

        if(!$query) {
            $errorInfo = $this->pdo->getPdo()->errorInfo();
            throw new \PDOException($errorInfo[2] . $errorInfo[0]);
        }

        try {
            $result = $query->execute();
        } catch(\PDOException $e) {
            $errorInfo = $this->pdo->getPdo()->errorInfo();
            throw new \PDOException($errorInfo[2], $errorInfo[0]);
        } finally {
            return $result;
        }
    }

    /**
     * @param array $primaryKey
     * @param EntityInterface $entity
     * @return boolean
     * @throws \PDOException
     */
    private function updateExisting(array $primaryKey, EntityInterface $entity)
    {
        $query = 'UPDATE `' . $entity->getTableName() . '` SET';

        foreach ($entity->getSetKeys() as $field)
        {
            $query .= '`' . $field . '` = ';
            $value = is_null($entity->get($field)) ? 'NULL, ' : '\'' . $entity->get($field) . '\', ';
            $query .= $value;
        }
        $query = substr($query, 0, -2);
        $query .= ' WHERE ';

        foreach($primaryKey as $idx => $key)
        {
            $query .= '`' . $idx . '` = \'' . $key . '\' AND ';
        }
        $query = substr($query, 0, -5);
        $query .= ';';

        $query = $this->pdo->getPdo()->prepare($query);

        try {
            $result = $query->execute();
        } catch(\PDOException $e) {
            $errorInfo = $this->pdo->getPdo()->errorInfo();
            throw new \PDOException($errorInfo[2], $errorInfo[0]);
        } finally {
            return $result;
        }
    }
}