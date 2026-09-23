<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Entity\Permission;

use Gingerminds\CoreBundle\Model\ResourceInterface;

interface PermissionInterface extends ResourceInterface
{
    public function getName(): ?string;

    public function setName(string $name): void;
}
