<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Entity\User;

use Gingerminds\CoreBundle\Enum\Civility;
use Gingerminds\CoreBundle\Model\ResourceInterface;

interface ContributorInterface extends ResourceInterface
{
    public function getFirstname(): ?string;

    public function setFirstname(?string $firstname): void;

    public function getLastname(): ?string;

    public function setLastname(?string $lastname): void;

    public function getTrigram(): ?string;

    public function setTrigram(?string $trigram): void;

    public function getCivility(): ?Civility;

    public function setCivility(?Civility $civility): void;

    public function getAvatar(): ?string;

    public function setAvatar(?string $avatar): void;

    public function getUser(): ?UserInterface;

    public function setUser(?UserInterface $user): void;

    public function getFullName(): string;
}
