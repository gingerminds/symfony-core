<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Command\Permission;

use Doctrine\ORM\EntityManagerInterface;
use Gingerminds\CoreBundle\Entity\Permission\PermissionInterface;
use Gingerminds\CoreBundle\Entity\Role\RoleInterface;
use Gingerminds\CoreBundle\Entity\User\UserInterface;
use Gingerminds\CoreBundle\Resource\ResourceRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'gingerminds:permissions:sync', description: 'Create missing permissions and base roles')]
final class SyncPermissionsCommand extends Command
{
    private const array CORE_PERMISSIONS = [
        'access admin',
        'view dashboard',
        'view settings',
        'manage roles',
    ];

    private const array ADMIN_PERMISSIONS = ['access admin', 'view dashboard'];

    /**
     * @param list<string> $extraPermissions
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ResourceRegistry $resources,
        private readonly array $extraPermissions,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $permissionClass = $this->resources->getEntityClass('permission');
        $roleClass = $this->resources->getEntityClass('role');

        $existing = [];

        foreach ($this->entityManager->getRepository($permissionClass)->findAll() as $permission) {
            if ($permission instanceof PermissionInterface) {
                $existing[(string) $permission->getName()] = $permission;
            }
        }

        $created = [];

        foreach ($this->expectedPermissions() as $name) {
            if (isset($existing[$name])) {
                continue;
            }

            /** @var PermissionInterface $permission */
            $permission = new $permissionClass();
            $permission->setName($name);
            $this->entityManager->persist($permission);
            $existing[$name] = $permission;
            $created[] = $name;
        }

        $roleRepository = $this->entityManager->getRepository($roleClass);

        foreach ([UserInterface::SUPER_ADMIN_ROLE => [], 'Admin' => self::ADMIN_PERMISSIONS] as $roleName => $permissions) {
            $role = $roleRepository->findOneBy(['name' => $roleName]);

            if ($role instanceof RoleInterface) {
                continue;
            }

            /** @var RoleInterface $role */
            $role = new $roleClass();
            $role->setName($roleName);

            foreach ($permissions as $name) {
                $role->addPermission($existing[$name]);
            }

            $this->entityManager->persist($role);
            $created[] = 'role ' . $roleName;
        }

        $this->entityManager->flush();

        if ([] === $created) {
            $io->success('Permissions and roles are up to date.');

            return Command::SUCCESS;
        }

        $io->listing($created);
        $io->success(\sprintf('%d permission(s)/role(s) created.', \count($created)));

        return Command::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function expectedPermissions(): array
    {
        $names = self::CORE_PERMISSIONS;

        foreach ($this->resources->all() as $resource) {
            if ('role' === $resource->name) {
                continue;
            }

            foreach (['view', 'edit', 'delete'] as $action) {
                $names[] = $action . ' ' . $resource->permission;
            }
        }

        return array_values(array_unique([...$names, ...$this->extraPermissions]));
    }
}
