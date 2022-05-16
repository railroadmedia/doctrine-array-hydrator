<?php

namespace Railroad\DoctrineArrayHydrator\Tests\Unit;

use Doctrine\Common\Cache\Psr6\DoctrineProvider;
use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Doctrine\ORM\EntityManager;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Railroad\DoctrineArrayHydrator\Contracts\UserProviderInterface;
use Railroad\DoctrineArrayHydrator\Tests\Fixtures\Functional\UserProvider;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

abstract class TestCase extends BaseTestCase
{

    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @var AnnotationReader
     */
    protected $annotationReader;

    /**
     * @param $object $object
     * @param string $propertyName
     * @return mixed
     */
    protected function getProtectedValue($object, $propertyName)
    {
        $reflectionObject = new \ReflectionObject($object);
        $property = $reflectionObject->getProperty($propertyName);
        $property->setAccessible(true);
        return $property->getValue($object);
    }

    /**
     * @param $object
     * @param $propertyName
     * @param $value
     */
    protected function setProtectedValue(&$object, $propertyName, $value)
    {
        $reflectionObject = new \ReflectionObject($object);
        $property = $reflectionObject->getProperty($propertyName);
        $property->setAccessible(true);
        $property->setValue($object, $value);
    }

    /**
     * @throws \Doctrine\ORM\ORMException
     */
    protected function setupDoctrine()
    {
        $databaseConfig = [
            'driver'=>'pdo_sqlite',
            'dbname'=>':memory:',
        ];
        $arrayCacheAdapter = new ArrayAdapter();
        $doctrineArrayCache = DoctrineProvider::wrap($arrayCacheAdapter);

        $doctrineConfig = \Doctrine\ORM\Tools\Setup::createAnnotationMetadataConfiguration(['tests/fixtures/'], false, getcwd().'/build/tmp', $doctrineArrayCache, false);
        $doctrineConfig->setAutoGenerateProxyClasses(true);

        $this->entityManager = EntityManager::create($databaseConfig, $doctrineConfig);
        $this->annotationReader = $this->entityManager->getConfiguration()->getMetadataDriverImpl();

        $userProvider = new UserProvider();
        app()->instance(UserProviderInterface::class, $userProvider);
    }

    /**
     * @param EntityManager $entityManager
     * @return array
     */
    protected function getEntityClassNames(EntityManager $entityManager)
    {
        $classes = [];

        /** @var ClassMetadata[] $metas */
        $metas = $entityManager->getMetadataFactory()->getAllMetadata();
        foreach ($metas as $meta) {
            $classes[] = $meta->getName();
        }

        return $classes;
    }

}