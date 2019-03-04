<?php

namespace Railroad\DoctrineArrayHydrator\Contracts;

interface UserProviderInterface
{
    /**
     * Returns false if the resourceType may be hydrated by UserProvider implementation
     */
    public function isTransient(string $resourceType): bool;

    /**
     * Creates a resource that is related to $entity
     * Populates the resource with data from $data array, typically a reference
     * Uses $relationName to create the camel case entity setter name
     * Calls the entity setter with the new resource
     */
    public function hydrateTransDomain(
        $entity,
        string $relationName,
        array $data
    ): void;
}
