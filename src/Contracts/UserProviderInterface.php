<?php

namespace Railroad\DoctrineArrayHydrator\Contracts;

interface UserProviderInterface
{
    public function isTransient(
        string $relationName,
        string $resourceType
    ): bool;

    public function hydrateTransDomain(
        $entity,
        string $relationName,
        array $data
    ): void;
}
