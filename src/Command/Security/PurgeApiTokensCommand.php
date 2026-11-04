<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Command\Security;

use Gingerminds\CoreBundle\Repository\Security\ApiTokenRepositoryInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Deletes the expired API tokens: rejected anyway, but each API login adds one.
 */
#[AsCommand(name: 'gingerminds:api-token:purge', description: 'Delete the expired API tokens')]
final class PurgeApiTokensCommand extends Command
{
    public function __construct(
        private readonly ApiTokenRepositoryInterface $tokens,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $deleted = $this->tokens->deleteExpired(\DateTimeImmutable::createFromInterface($this->clock->now()));
        new SymfonyStyle($input, $output)->success(\sprintf('%d expired API token(s) deleted.', $deleted));

        return Command::SUCCESS;
    }
}
