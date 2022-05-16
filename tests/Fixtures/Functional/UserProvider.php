<?php

namespace Railroad\DoctrineArrayHydrator\Tests\Fixtures\Functional;

use Doctrine\Common\Inflector\Inflector;
use Doctrine\Inflector\InflectorFactory;
use Illuminate\Support\Facades\DB;
use Railroad\DoctrineArrayHydrator\Contracts\UserProviderInterface;
use Railroad\DoctrineArrayHydrator\Tests\Fixtures\Functional\User;

class UserProvider implements UserProviderInterface
{
    /**
     * @var \Doctrine\Inflector\Inflector
     */
    protected $inflector;

    CONST RESOURCE_TYPE = 'user';

    public function __construct()
    {
        $this->inflector = InflectorFactory::create()->build();
    }

    public function getUserById(int $id): ?User
    {
        $user = DB::table('users')->find($id);

        if ($user) {
            return new User($id, $user->name);
        }

        return null;
    }

    public function isTransient(string $resourceType): bool {

        return $resourceType !== self::RESOURCE_TYPE;
    }

    public function hydrateTransDomain(
        $entity,
        string $relationName,
        array $data
    ): void {

        $setterName = $this->inflector->camelize('set' . ucwords($relationName));

        if (
            isset($data['data']['type']) &&
            $data['data']['type'] === self::RESOURCE_TYPE &&
            isset($data['data']['id']) &&
            is_object($entity) &&
            method_exists($entity, $setterName)
        ) {

            $user = $this->getUserById($data['data']['id']);

            call_user_func([$entity, $setterName], $user);
        }

        // else some exception should be thrown
    }
}
