<?php

namespace Railroad\DoctrineArrayHydrator\Tests\Unit;

use Doctrine\ORM\EntityManager;
use Mockery as m;
use Railroad\DoctrineArrayHydrator\ArrayHydrator;
use Railroad\DoctrineArrayHydrator\Tests\Fixtures\Address;
use Railroad\DoctrineArrayHydrator\Tests\Fixtures\Call;
use Railroad\DoctrineArrayHydrator\Tests\Fixtures\Company;
use Railroad\DoctrineArrayHydrator\Tests\Fixtures\Permission;
use Railroad\DoctrineArrayHydrator\Tests\Fixtures\User;

class ArrayHydratorTest extends TestCase
{
    /**
     * @var ArrayHydrator
     */
    protected $hydrator;

    /**
     * @throws \Doctrine\ORM\ORMException
     */
    public function setUp()
    {
        $this->setupDoctrine();
        $this->hydrator = new ArrayHydrator($this->entityManager);
    }

    public function testHydrateProperties()
    {
        $data = [
            'id' => 1,
            'name' => 'Fred Jones',
            'email' => 'fred@example.org',
        ];

        $user = new User();
        $user = $this->hydrator->hydrate($user, $data);

        $this->assertEquals($data['id'], $user->getId());
        $this->assertEquals($data['name'], $user->getName());
        $this->assertEquals($data['email'], $user->getEmail());
    }


    public function testHydratePropertiesWithCamelize()
    {
        $data = [
            'street_address' => 'Road',
        ];

        $address = new Address();
        $address = $this->hydrator->hydrate($address, $data);

        $this->assertEquals($data['street_address'], $address->getStreetAddress());
    }

    public function testHydrateManyToOneAssociation()
    {
        $data = [
            'company' => 1,
            'address' => 103,
        ];

        $user = new User();
        /** @var User() $user */
        $user = $this->hydrator->hydrate($user, $data);

        $this->assertEquals(1,
            $user->getCompany()
                ->getId()
        );
        $this->assertEquals(103,
            $user->getAddress()
                ->getId()
        );
    }

    public function testHydrateOneToManyAssociations()
    {
        $data = [
            'permissions' => [1, 2, 3, 4, 5],
        ];

        $user = new User();
        /** @var User() $user */
        $user = $this->hydrator->hydrate($user, $data);

        $permissions = $user->getPermissions();
        $this->assertEquals(1, $permissions[0]->getId());
        $this->assertEquals(2, $permissions[1]->getId());
        $this->assertEquals(3, $permissions[2]->getId());
        $this->assertEquals(4, $permissions[3]->getId());
        $this->assertEquals(5, $permissions[4]->getId());
    }

    public function testHydrateOneToManyObjects()
    {
        $data = [
            'name' => 'George',
            'permissions' => [
                ['name' => 'New Permission 1'],
                ['name' => 'New Permission 2'],
            ],
        ];

        $user = new User();
        /** @var User() $user */
        $user = $this->hydrator->hydrate($user, $data);

        $this->assertEquals($data['name'], $user->getName());

        $permissions = $user->getPermissions();
        foreach ($permissions as $permission) {
            $this->assertInstanceOf(Permission::class, $permission);
        }

        $this->assertEquals($data['permissions'][0]['name'], $permissions[0]->getName());
        $this->assertEquals($data['permissions'][1]['name'], $permissions[1]->getName());
    }

    public function testHydrateAll()
    {
        $data = [
            'id' => 1,
            'name' => 'Fred Jones',
            'email' => 'fred@example.org',
            'company' => 1,
            'permissions' => [1, 2, 3, 4, 5],
        ];

        $user = new User();
        /** @var User() $user */
        $user = $this->hydrator->hydrate($user, $data);

        $this->assertEquals($data['id'], $user->getId());
        $this->assertEquals($data['name'], $user->getName());
        $this->assertEquals($data['email'], $user->getEmail());

        $this->assertEquals(1,
            $user->getCompany()
                ->getId()
        );

        $permissions = $user->getPermissions();
        $this->assertEquals(1, $permissions[0]->getId());
        $this->assertEquals(2, $permissions[1]->getId());
        $this->assertEquals(3, $permissions[2]->getId());
        $this->assertEquals(4, $permissions[3]->getId());
        $this->assertEquals(5, $permissions[4]->getId());
    }

    /**
     * @expectedException \Exception
     */
    public function testInvalidClass()
    {
        $this->hydrator->hydrate(1, []);
    }

    /**
     * @expectedException \Exception
     */
    public function testUnkownClass()
    {
        $this->hydrator->hydrate('An\Unknown\Class', []);
    }

    public function testFetchAssociationEntity()
    {
        /** @var User() $user */
        $user = new User();
        $company = new Company();
        $company->setId(1);
        $company->setName('testing');

        /** @var EntityManager $entityManagerMock */
        $entityManagerMock =
            m::mock($this->entityManager)
                ->shouldReceive('find')
                ->with(Company::class, 1)
                ->andReturn($company)
                ->getMock();

        $this->hydrator = new ArrayHydrator($entityManagerMock);
        $this->hydrator->setHydrateAssociationReferences(false);
        $user = $this->hydrator->hydrate($user, ['company' => $company->getId()]);

        $this->assertEquals($company->getId(),
            $user->getCompany()
                ->getId()
        );
        $this->assertEquals($company->getName(),
            $user->getCompany()
                ->getName()
        );
    }

    public function testConvertType()
    {
        $data = [
            'id' => '1',
            'duration' => '50',
            'startTime' => '2017-10-23 17:57:00',
            'status' => 'true',
        ];

        $call = new Call();
        $call = $this->hydrator->hydrate($call, $data);

        $this->assertInternalType('integer', $call->getDuration());
        $this->assertInstanceOf(\DateTime::class, $call->getStartTime());
        $this->assertInternalType('bool', $call->isStatus());
    }
}
