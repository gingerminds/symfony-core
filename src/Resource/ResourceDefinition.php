<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Resource;

use Gingerminds\CoreBundle\Security\Voter\ResourceVoter;
use Symfony\Component\Form\FormTypeInterface;

final readonly class ResourceDefinition
{
    /**
     * @param class-string                                $entity
     * @param class-string<FormTypeInterface<mixed>>|null $form
     * @param array<string, string>                       $permissions permission name by ResourceVoter attribute, replacing the convention
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
        public RedirectTarget $redirectAfterNew = RedirectTarget::Index,
        public RedirectTarget $redirectAfterEdit = RedirectTarget::Edit,
        public array $permissions = [],
    ) {
    }

    /**
     * @param array{entity: class-string, controller?: string|null, form?: class-string<FormTypeInterface<mixed>>|null, path: string, permission: string, route_prefix: string, translation_prefix: string, translation_domain: string, template_prefix: string, redirect_after_new?: string, redirect_after_edit?: string, permissions?: array<string, string>} $config
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
            redirectAfterNew: RedirectTarget::from($config['redirect_after_new'] ?? RedirectTarget::Index->value),
            redirectAfterEdit: RedirectTarget::from($config['redirect_after_edit'] ?? RedirectTarget::Edit->value),
            permissions: $config['permissions'] ?? [],
        );
    }

    /**
     * The permission granting a ResourceVoter attribute: `view <permission>` (VIEW),
     * `edit <permission>` (CREATE, EDIT), `delete <permission>` (DELETE), unless mapped otherwise
     * by `permissions` (e.g. `manage roles` for all of them).
     */
    public function permissionFor(string $attribute): string
    {
        return $this->permissions[$attribute] ?? match ($attribute) {
            ResourceVoter::VIEW => 'view ' . $this->permission,
            ResourceVoter::CREATE, ResourceVoter::EDIT => 'edit ' . $this->permission,
            ResourceVoter::DELETE => 'delete ' . $this->permission,
            default => throw new \InvalidArgumentException(\sprintf(
                'Unknown resource attribute "%s", expected one of "%s".',
                $attribute,
                implode('", "', ResourceVoter::ATTRIBUTES),
            )),
        };
    }

    /**
     * The permissions of the resource (gingerminds:permissions:sync).
     *
     * @return list<string>
     */
    public function permissionNames(): array
    {
        return array_values(array_unique(array_map($this->permissionFor(...), ResourceVoter::ATTRIBUTES)));
    }

    public function redirectAfterSave(bool $isNew): RedirectTarget
    {
        return $isNew ? $this->redirectAfterNew : $this->redirectAfterEdit;
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
