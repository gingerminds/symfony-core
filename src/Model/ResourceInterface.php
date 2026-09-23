<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Model;

interface ResourceInterface
{
    public function getId(): int|string|null;
}
