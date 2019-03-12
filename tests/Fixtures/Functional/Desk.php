<?php

namespace Railroad\DoctrineArrayHydrator\Tests\Fixtures\Functional;

use Doctrine\ORM\Mapping as ORM;
use Railroad\DoctrineArrayHydrator\Contracts\UserProviderInterface;
use Railroad\DoctrineArrayHydrator\Tests\Fixtures\Functional\User;
use Railroad\DoctrineArrayHydrator\Tests\Fixtures\Functional\Office;
use function app;

/**
 * @ORM\Entity
 * @ORM\Table(name="desks")
 */
class Desk
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue
     *
     * @var int
     */
    protected $id;

    /**
     * @ORM\Column(type="string", name="inventory_id")
     *
     * @var string
     */
    protected $inventoryId;

    /**
     * @ORM\Column(type="integer", name="user_id")
     *
     * @var int
     */
    protected $userId;

    /**
     * @ORM\ManyToOne(targetEntity="Office")
     * @ORM\JoinColumn(name="office_id", referencedColumnName="id")
     *
     * @var Office
     */
    protected $office;

    /**
     * @return int
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @param int $id
     *
     * @return Desk
     */
    public function setId(int $id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @return string
     */
    public function getInventoryId(): ?string
    {
        return $this->inventoryId;
    }

    /**
     * @param string $inventoryId
     *
     * @return Desk
     */
    public function setInventoryId(string $inventoryId)
    {
        $this->inventoryId = $inventoryId;

        return $this;
    }

    /**
     * @return User
     */
    public function getUser(): ?User
    {
        /**
         * @var $userProvider \Railroad\DoctrineArrayHydrator\Contracts\UserProviderInterface
         */
        $userProvider = app()->make(UserProviderInterface::class);

        // test solution, doctrine custom types should be used
        return $userProvider->getUserById($this->userId);
    }

    /**
     * @param User $user
     *
     * @return Desk
     */
    public function setUser(?User $user)
    {
        // test solution, doctrine custom types should be used
        $this->userId = $user->getId();

        return $this;
    }

    /**
     * @return Office
     */
    public function getOffice(): ?Office
    {
        return $this->office;
    }

    /**
     * @param Office $office
     *
     * @return Desk
     */
    public function setOffice(Office $office)
    {
        $this->office = $office;

        return $this;
    }
}
