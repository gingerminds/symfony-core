<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Tests\Functional\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Gingerminds\CoreBundle\Entity\Role\Role;
use Gingerminds\CoreBundle\Repository\Role\RoleRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * RoleRepository::save(): one default role per audience, never lost on a failed flush.
 */
final class RoleRepositoryTest extends KernelTestCase
{
    public function testAFailedSaveKeepsThePreviousDefaultRole(): void
    {
        $roles = self::getContainer()->get(RoleRepository::class);
        $first = $this->role('First default', default: true);
        $roles->save($first);

        // Same name: the flush fails on the unique name, after the previous default was unset.
        $duplicate = $this->role('First default', default: true);

        try {
            $roles->save($duplicate);
            self::fail('The duplicate role was saved.');
        } catch (UniqueConstraintViolationException) {
        }

        self::assertSame(
            [['name' => 'First default', 'is_default' => 1]],
            self::getContainer()->get(Connection::class)->fetchAllAssociative('SELECT name, is_default FROM roles WHERE is_default IS NOT NULL'),
        );
    }

    public function testANewDefaultRoleReplacesThePreviousOne(): void
    {
        $roles = self::getContainer()->get(RoleRepository::class);
        $first = $this->role('First default', default: true);
        $roles->save($first);
        $second = $this->role('Second default', default: true);
        $roles->save($second);

        self::getContainer()->get(EntityManagerInterface::class)->refresh($first);

        self::assertFalse($first->isDefault());
        self::assertTrue($second->isDefault());
    }

    private function role(string $name, bool $default): Role
    {
        $role = new Role();
        $role->setName($name);
        $role->setDefault($default);

        return $role;
    }
}
