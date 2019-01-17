<?php

namespace Railroad\DoctrineArrayHydrator;

use Doctrine\DBAL\DBALException;
use Exception;
use ReflectionException;

/**
 * Json API Request Doctrine Hydrator
 *
 * @link http://jsonapi.org/format/#document-resource-objects
 */
class JsonApiHydrator extends ArrayHydrator
{
    /**
     * @param $entity
     * @param $data
     *
     * @return object
     * @throws DBALException
     * @throws ReflectionException
     */
    protected function hydrateProperties($entity, $data)
    {
        if (isset($data['data'])) {
            $data = $data['data'];
        }

        if (isset($data['id'])) {
            $data['attributes']['id'] = $data['id'];
        }

        if (isset($data['attributes']) && is_array($data['attributes'])) {
            $data['attributes'] = $this->camelizeArray($data['attributes']);

            $entity = parent::hydrateProperties($entity, $data['attributes']);
        }

        return $entity;
    }

    /**
     * Map JSON API resource relations to doctrine entity.
     *
     * @param object $entity
     * @param array $data
     *
     * @return object
     * @throws Exception
     */
    protected function hydrateAssociations($entity, $data)
    {
        if (isset($data['data'])) {
            // set 'relationships' as top level key - https://jsonapi.org/format/#document-resource-objects
            $data = $data['data'];
        }

        if (isset($data['relationships']) && is_array($data['relationships'])) {
            $metadata = $this->entityManager->getClassMetadata(get_class($entity));

            foreach ($data['relationships'] as $name => $data) {
                if (!isset($metadata->associationMappings[$name])) {
                    throw new Exception(sprintf('Relation `%s` association not found', $name));
                }

                $mapping = $metadata->associationMappings[$name];

                if (is_array($data['data'])) {
                    if ($resourceId = $this->getResourceId($data['data'])) {
                        $this->hydrateToOneAssociation($entity, $name, $mapping, $resourceId);
                    } else {
                        $this->hydrateToManyAssociation(
                            $entity,
                            $name,
                            $mapping,
                            $this->mapRelationshipsArray($data['data'])
                        );
                    }
                }
            }
        }

        return $entity;
    }

    /**
     * @param array $data
     *
     * @return array
     */
    protected function mapRelationshipsArray(array $data)
    {
        return array_map(
            function ($relation) {
                return $this->getResourceId($relation) ?: ['attributes' => $relation];
            },
            $data
        );
    }

    /**
     * @param array $data
     *
     * @return int|null
     */
    protected function getResourceId(array $data)
    {
        if (isset($data['id']) && isset($data['type'])) {
            return $data['id'];
        }

        return null;
    }
}
