<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\ApiPlatform\Metadata;

use ApiPlatform\Metadata\HeaderParameter;

interface HeaderParameterProviderInterface
{
    /**
     * @return class-string
     */
    public function getMarker(): string;

    public function getHeaderParameter(): HeaderParameter;
}
