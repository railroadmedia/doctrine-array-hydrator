<?php

namespace Railroad\DoctrineArrayHydrator;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Inflector\Inflector;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\ORMException;
use Exception;
use ReflectionException;
use ReflectionObject;

class ArrayHydrator
{
    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * If true, then associations are filled only with reference proxies. This is faster than querying them from
     * database, but if the associated entity does not really exist, it will cause:
     * * The insert/update to fail, if there is a foreign key defined in database
     * * The record ind database also pointing to a non-existing record
     *
     * @var bool
     */
    protected $hydrateAssociationReferences = true;

    /**
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * @param object $entity
     * @param array $data
     * @return object
     *
     * @throws DBALException
     * @throws ORMException
     * @throws ReflectionException
     * @throws Exception
     */
    public function hydrate($entity, array $data)
    {
        if (is_string($entity) && class_exists($entity)) {
            $entity = new $entity;
        } elseif (!is_object($entity)) {
            throw new Exception('Entity passed to ArrayHydrator::hydrate() must be a class name or entity object');
        }

        $data = $this->camelizeArray($data);

        $entity = $this->hydrateProperties($entity, $data);
        $entity = $this->hydrateAssociations($entity, $data);

        return $entity;
    }

    /**
     * @param boolean $hydrateAssociationReferences
     */
    public function setHydrateAssociationReferences($hydrateAssociationReferences)
    {
        $this->hydrateAssociationReferences = $hydrateAssociationReferences;
    }

    /**
     * @param object $entity the doctrine entity
     * @param array $data
     * @return object
     *
     * @throws DBALException
     * @throws ReflectionException
     */
    protected function hydrateProperties($entity, $data)
    {
        $reflectionObject = new ReflectionObject($entity);

        $metaData = $this->entityManager->getClassMetadata(get_class($entity));

        $platform =
            $this->entityManager->getConnection()
                ->getDatabasePlatform();

        foreach ($metaData->fieldNames as $fieldName) {
            $dataKey = Inflector::camelize($fieldName);

            if (array_key_exists($dataKey, $data)) {
                $value = $data[$dataKey];

                if (array_key_exists('type', $metaData->fieldMappings[$fieldName])) {
                    $fieldType = $metaData->fieldMappings[$fieldName]['type'];

                    $type = Type::getType($fieldType);

                    $value = $type->convertToPHPValue($value, $platform);
                }

                $entity = $this->setProperty($entity, $fieldName, $value, $reflectionObject);
            }
        }

        return $entity;
    }

    /**
     * @param $entity
     * @param $data
     * @return object
     *
     * @throws DBALException
     * @throws ORMException
     * @throws ReflectionException
     */
    protected function hydrateAssociations($entity, $data)
    {
        $metaData = $this->entityManager->getClassMetadata(get_class($entity));

        foreach ($metaData->associationMappings as $fieldName => $mapping) {
            $associationData = $this->getAssociatedId($fieldName, $data);

            if (!empty($associationData)) {
                if (in_array($mapping['type'], [ClassMetadataInfo::ONE_TO_ONE, ClassMetadataInfo::MANY_TO_ONE])) {
                    $entity = $this->hydrateToOneAssociation($entity, $fieldName, $mapping, $associationData);
                }

                if (in_array($mapping['type'], [ClassMetadataInfo::ONE_TO_MANY, ClassMetadataInfo::MANY_TO_MANY])) {
                    $entity = $this->hydrateToManyAssociation($entity, $fieldName, $mapping, $associationData);
                }
            }
        }

        return $entity;
    }

    /**
     * Retrieves the associated entity's id from $data
     *
     * @param string $fieldName name of field that stores the associated entity
     * @param array $data the hydration data
     *
     * @return integer|null null if the association is not found
     */
    protected function getAssociatedId($fieldName, $data)
    {
        if (isset($data[$fieldName])) {
            return $data[$fieldName];
        }

        return isset($data[$fieldName]) ? $data[$fieldName] : null;
    }

    /**
     * @param $entity
     * @param $propertyName
     * @param $mapping
     * @param $value
     * @return object
     *
     * @throws ORMException
     * @throws ReflectionException
     */
    protected function hydrateToOneAssociation($entity, $propertyName, $mapping, $value)
    {
        $reflectionObject = new ReflectionObject($entity);

        $toOneAssociationObject = $this->fetchAssociationEntity($mapping['targetEntity'], $value);
        if (!is_null($toOneAssociationObject)) {
            $entity = $this->setProperty($entity, $propertyName, $toOneAssociationObject, $reflectionObject);
        }

        return $entity;
    }

    /**
     * @param $entity
     * @param $propertyName
     * @param $mapping
     * @param $value
     * @return object
     *
     * @throws DBALException
     * @throws ORMException
     * @throws ReflectionException
     */
    protected function hydrateToManyAssociation($entity, $propertyName, $mapping, $value)
    {
        $reflectionObject = new ReflectionObject($entity);
        $values = is_array($value) ? $value : [$value];

        $associationObjects = new ArrayCollection();

        foreach ($values as $value) {
            if (is_array($value)) {
                $associationObjects[] = $this->hydrate($mapping['targetEntity'], $value);
            } elseif ($associationObject = $this->fetchAssociationEntity($mapping['targetEntity'], $value)) {
                $associationObjects[] = $associationObject;
            }
        }

        $entity = $this->setProperty($entity, $propertyName, $associationObjects, $reflectionObject);

        return $entity;
    }

    /**
     * @param $entity
     * @param $propertyName
     * @param $value
     * @param null $reflectionObject
     * @return object
     *
     * @throws ReflectionException
     */
    protected function setProperty($entity, $propertyName, $value, $reflectionObject = null)
    {
        // use the setter if it exists, otherwise use reflection
        $getFunction = Inflector::camelize('set' . ucwords($propertyName));

        if (method_exists($entity, $getFunction)) {
            call_user_func([$entity, $getFunction], $value);

            return $entity;
        }

        $reflectionObject = is_null($reflectionObject) ? new ReflectionObject($entity) : $reflectionObject;

        $property = $reflectionObject->getProperty($propertyName);

        $property->setAccessible(true);
        $property->setValue($entity, $value);

        return $entity;
    }

    /**
     * @param $className
     * @param $id
     * @return bool|\Doctrine\Common\Proxy\Proxy|null|object
     *
     * @throws ORMException
     */
    protected function fetchAssociationEntity($className, $id)
    {
        if ($this->hydrateAssociationReferences) {
            return $this->entityManager->getReference($className, $id);
        }

        return $this->entityManager->find($className, $id);
    }

    /**
     * @param array $array
     * @return array
     */
    protected function camelizeArray(array $array)
    {
        $camelizedArray = [];

        foreach ($array as $valueIndex => $value) {
            $camelizedArray[Inflector::camelize($valueIndex)] = $value;
        }

        return $camelizedArray;
    }
}
