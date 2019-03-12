<?php

namespace Railroad\DoctrineArrayHydrator\Tests\Functional;

use Exception;
use Railroad\DoctrineArrayHydrator\JsonApiHydrator;
use Railroad\DoctrineArrayHydrator\Tests\Fixtures\Functional\Desk;
use Railroad\DoctrineArrayHydrator\Tests\Fixtures\Functional\Office;
use Railroad\DoctrineArrayHydrator\Tests\Fixtures\Functional\User;

class JsonApiHydratorTest extends TestCase
{
    /**
     * Custom association hydration
     */

    public function testDoctrineManagedAssociationHydration()
    {
        $hydrator = app()->make(JsonApiHydrator::class);

        $office = $this->seedOffice($this->faker->word);

        $data = [
            'attributes' => [
                'inventoryId' => $this->faker->word,
            ],
            'relationships' => [
                'office' => [
                    'data' => [
                        'type' => 'office',
                        'id' => $office->getId()
                    ],
                ],
            ],
        ];

        $desk = new Desk();

        $desk = $hydrator->hydrate($desk, $data);

        $deskOffice = $desk->getOffice();

        $this->assertNotNull($deskOffice);

        $this->assertInstanceOf(Office::class, $deskOffice);

        $this->assertEquals($office, $deskOffice);

    }

    public function testDoctrineManagedEntityAssociationObjectHydration()
    {
        $hydrator = app()->make(JsonApiHydrator::class);

        $user = $this->seedUser($this->faker->word);

        $data = [
            'attributes' => [
                'inventoryId' => $this->faker->word,
            ],
            'relationships' => [
                'user' => [
                    'data' => [
                        'type' => 'user',
                        'id' => $user->getId()
                    ],
                ],
            ],
        ];

        $desk = new Desk();

        $desk = $hydrator->hydrate($desk, $data);

        $this->entityManager->persist($desk);

        $this->entityManager->flush();

        $this->assertDatabaseHas(
            'desks',
            [
                'id' => $desk->getId(),
                'inventory_id' => $data['attributes']['inventoryId'],
                'user_id' => $user->getId()
            ]
        );

        $deskUser = $desk->getUser();

        $this->assertNotNull($deskUser);

        $this->assertInstanceOf(User::class, $deskUser);

        $this->assertEquals($user, $deskUser);
    }

    public function testDoctrineManagedEntityAssociationUndefinedHydration()
    {
        $hydrator = app()->make(JsonApiHydrator::class);

        $data = [
            'attributes' => [
                'inventoryId' => $this->faker->word,
            ],
            'relationships' => [
                'building' => [
                    'data' => [
                        'type' => 'building',
                        'id' => rand()
                    ],
                ],
            ],
        ];

        $desk = new Desk();

        $this->expectException(Exception::class);

        $desk = $hydrator->hydrate($desk, $data);
    }
    
    protected function seedOffice(string $name)
    {
        $office = new Office();

        $office->setName($name);

        $this->entityManager->persist($office);

        $this->entityManager->flush();

        return $office;
    }

    protected function seedUser(string $name)
    {
        $userId = $this->databaseManager
            ->table('users')
            ->insertGetId(['name' => $name]);

        $user = new User();

        return $user
                    ->setId($userId)
                    ->setName($name);
    }
}
