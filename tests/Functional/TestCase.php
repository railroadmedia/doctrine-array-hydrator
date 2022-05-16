<?php

namespace Railroad\DoctrineArrayHydrator\Tests\Functional;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Cache\ArrayCache;
use Doctrine\Common\Cache\Psr6\DoctrineProvider;
use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Setup;
use Faker\Factory;
use Faker\Generator;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Railroad\DoctrineArrayHydrator\Contracts\UserProviderInterface;
use Railroad\DoctrineArrayHydrator\Tests\Fixtures\Functional\UserProvider;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

abstract class TestCase extends BaseTestCase
{
    protected $entityManager;

    protected $databaseManager;

    /**
     * @var Generator
     */
    protected $faker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->faker = Factory::create();

        $userProvider = new UserProvider();

        app()->instance(UserProviderInterface::class, $userProvider);

        $this->setupDoctrine();

        app()->instance(EntityManagerInterface::class, $this->entityManager);

        $this->setupLaravelDatabase();
    }

    protected function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'sqlite');
        config()->set(
            'database.connections.sqlite',
            [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ]
        );
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

        $doctrineConfig = Setup::createAnnotationMetadataConfiguration(
            ['tests/fixtures/functional'],
            false,
            getcwd().'/build/tmp',
            $doctrineArrayCache,
            false
        );

        $doctrineConfig->setAutoGenerateProxyClasses(true);

        $this->entityManager = EntityManager::create($databaseConfig, $doctrineConfig);
        $this->annotationReader = $this->entityManager->getConfiguration()->getMetadataDriverImpl();
    }

    protected function setupLaravelDatabase()
    {
        DB::connection()
            ->setPdo(
                $this->entityManager->getConnection()->getNativeConnection()
            );

        DB::connection()
            ->setReadPdo(
                $this->entityManager->getConnection()->getNativeConnection()
            );

        Schema::create(
            'users',
            function (Blueprint $table) {
                $table->temporary();
                $table->increments('id');
                $table->string('name');
            }
        );

        Schema::create(
            'offices',
            function (Blueprint $table) {
                $table->temporary();
                $table->increments('id');
                $table->string('name');
            }
        );

        Schema::create(
            'desks',
            function (Blueprint $table) {
                $table->temporary();
                $table->increments('id');
                $table->integer('inventory_id');
                $table->integer('user_id');
                $table->integer('office_id')->nullable();
            }
        );

        $this->databaseManager = $this->app->make(DatabaseManager::class);
    }
}
