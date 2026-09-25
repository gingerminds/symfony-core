<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Command\User;

use Doctrine\ORM\EntityManagerInterface;
use Gingerminds\CoreBundle\Entity\Role\RoleInterface;
use Gingerminds\CoreBundle\Entity\User\ContributorInterface;
use Gingerminds\CoreBundle\Entity\User\UserInterface;
use Gingerminds\CoreBundle\Resource\ResourceRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(name: 'gingerminds:create:user', description: 'Create a user with its contributor and a role')]
final class CreateUserCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ResourceRegistry $resources,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ValidatorInterface $validator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Email (login)')
            ->addOption('role', null, InputOption::VALUE_REQUIRED, 'Role name')
            ->addOption('lastname', null, InputOption::VALUE_REQUIRED, 'Last name')
            ->addOption('firstname', null, InputOption::VALUE_REQUIRED, 'First name')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Password (asked when omitted)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $roles = array_values(array_filter(
            $this->entityManager->getRepository($this->resources->getEntityClass('role'))->findBy([], ['name' => 'ASC']),
            static fn (object $role): bool => $role instanceof RoleInterface,
        ));
        $roleNames = array_map(static fn (RoleInterface $role): string => (string) $role->getName(), $roles);

        if ([] === $roleNames) {
            $io->error('No role found: run "bin/console gingerminds:permissions:sync" first.');

            return Command::FAILURE;
        }

        $email = $input->getOption('email') ?? $io->ask('Email');
        $roleName = $input->getOption('role') ?? $io->choice('Role', $roleNames, $roleNames[0]);
        $lastname = $input->getOption('lastname') ?? $io->ask('Last name');
        $firstname = $input->getOption('firstname') ?? $io->ask('First name');
        $password = $input->getOption('password') ?? $io->askHidden('Password');

        $role = $roles[array_search($roleName, $roleNames, true)] ?? null;

        if (!$role instanceof RoleInterface) {
            $io->error(\sprintf('Unknown role "%s".', $roleName));

            return Command::FAILURE;
        }

        $userClass = $this->resources->getEntityClass('user');
        $contributorClass = $this->resources->getEntityClass('contributor');

        /** @var UserInterface $user */
        $user = new $userClass();
        $user->setEmail((string) $email);
        $user->setPlainPassword((string) $password);
        $user->addRoleEntity($role);

        /** @var ContributorInterface $contributor */
        $contributor = new $contributorClass();
        $contributor->setLastname((string) $lastname);
        $contributor->setFirstname((string) $firstname);

        return $this->saveUser($io, $user, $contributor, (string) $password, (string) $roleName);
    }

    /**
     * Validates the user, then hashes its password and persists it with its contributor.
     */
    private function saveUser(SymfonyStyle $io, UserInterface $user, ContributorInterface $contributor, string $password, string $roleName): int
    {
        $violations = $this->validator->validate($user);

        if (\count($violations) > 0) {
            foreach ($violations as $violation) {
                $io->error($violation->getPropertyPath() . ': ' . $violation->getMessage());
            }

            return Command::FAILURE;
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $user->setPlainPassword(null);

        $this->entityManager->wrapInTransaction(function () use ($user, $contributor): void {
            $this->entityManager->persist($user);
            $contributor->setUser($user);
            $this->entityManager->persist($contributor);
        });

        $io->success(\sprintf('User "%s" created with role "%s".', $user->getEmail(), $roleName));

        return Command::SUCCESS;
    }
}
