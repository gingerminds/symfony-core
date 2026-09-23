<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Resource;

use Symfony\Component\Form\FormTypeInterface;

final readonly class ResourceDefinition
{
    /**
     * @param class-string                                $entity
     * @param class-string<FormTypeInterface<mixed>>|null $form
     */
    public function __construct(
        public string $name,
        public string $entity,
        public ?string $controller,
        public ?string $form,
        public string $path,
        public string $permission,
        public string $routePrefix,
        public string $translationPrefix,
        public string $translationDomain,
        public string $templatePrefix,
    ) {
    }

    /**
     * @param array{entity: class-string, controller?: string|null, form?: class-string<FormTypeInterface<mixed>>|null, path: string, permission: string, route_prefix: string, translation_prefix: string, translation_domain: string, template_prefix: string} $config
     */
    public static function fromArray(string $name, array $config): self
    {
        return new self(
            name: $name,
            entity: $config['entity'],
            controller: $config['controller'] ?? null,
            form: $config['form'] ?? null,
            path: $config['path'],
            permission: $config['permission'],
            routePrefix: $config['route_prefix'],
            translationPrefix: $config['translation_prefix'],
            translationDomain: $config['translation_domain'],
            templatePrefix: $config['template_prefix'],
        );
    }

    public function route(string $action): string
    {
        return $this->routePrefix . '_' . $action;
    }

    public function template(string $name): string
    {
        return $this->templatePrefix . '/' . $name . '.html.twig';
    }

    public function translationKey(string $key): string
    {
        return $this->translationPrefix . '.' . $key;
    }
}
