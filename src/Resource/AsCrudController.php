<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Resource;

#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class AsCrudController
{
    /**
     * @param class-string      $entity
     * @param class-string|null $form
     */
    public function __construct(
        public string $resource,
        public string $entity,
        public ?string $form = null,
        public ?string $path = null,
        public ?string $permission = null,
        public ?string $routePrefix = null,
        public ?string $translationPrefix = null,
        public ?string $translationDomain = null,
        public ?string $templatePrefix = null,
    ) {
    }
}
