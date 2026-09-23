<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle;

use Gingerminds\CoreBundle\ApiPlatform\Metadata\HeaderParameterProviderInterface;
use Gingerminds\CoreBundle\Controller\Permission\PermissionController;
use Gingerminds\CoreBundle\Controller\Role\RoleController;
use Gingerminds\CoreBundle\Controller\User\ContributorController;
use Gingerminds\CoreBundle\Controller\User\UserController;
use Gingerminds\CoreBundle\DependencyInjection\Compiler\CoreEntityPass;
use Gingerminds\CoreBundle\DependencyInjection\Compiler\ResourceRegistryPass;
use Gingerminds\CoreBundle\Entity\Permission\Permission;
use Gingerminds\CoreBundle\Entity\Permission\PermissionInterface;
use Gingerminds\CoreBundle\Entity\Role\Role;
use Gingerminds\CoreBundle\Entity\Role\RoleInterface;
use Gingerminds\CoreBundle\Entity\User\Contributor;
use Gingerminds\CoreBundle\Entity\User\ContributorInterface;
use Gingerminds\CoreBundle\Entity\User\User;
use Gingerminds\CoreBundle\Entity\User\UserInterface;
use Gingerminds\CoreBundle\Form\Permission\PermissionType;
use Gingerminds\CoreBundle\Form\Role\RoleType;
use Gingerminds\CoreBundle\Form\User\ContributorType;
use Gingerminds\CoreBundle\Form\User\UserType;
use Gingerminds\CoreBundle\Menu\AdminMenuProviderInterface;
use Gingerminds\CoreBundle\Repository\Filter\AsFilterHandler;
use Gingerminds\CoreBundle\Resource\AsCrudController;
use Gingerminds\CoreBundle\Security\Api\LoginResponseEnricherInterface;
use Symfony\Bundle\MakerBundle\MakerBundle;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class GingermindsCoreBundle extends AbstractBundle
{
    public const array CORE_RESOURCES = [
        'user' => [
            'entity' => User::class,
            'interface' => UserInterface::class,
            'controller' => UserController::class,
            'form' => UserType::class,
            'path' => 'users',
            'permission' => 'users',
        ],
        'contributor' => [
            'entity' => Contributor::class,
            'interface' => ContributorInterface::class,
            'controller' => ContributorController::class,
            'form' => ContributorType::class,
            'path' => 'contributors',
            'permission' => 'contributors',
        ],
        'role' => [
            'entity' => Role::class,
            'interface' => RoleInterface::class,
            'controller' => RoleController::class,
            'form' => RoleType::class,
            'path' => 'roles',
            'permission' => 'roles',
        ],
        'permission' => [
            'entity' => Permission::class,
            'interface' => PermissionInterface::class,
            'controller' => PermissionController::class,
            'form' => PermissionType::class,
            'path' => 'permissions',
            'permission' => 'permissions',
        ],
    ];

    protected string $extensionAlias = 'gingerminds_core';

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->import('../config/definition.php');
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new ResourceRegistryPass());
        $container->addCompilerPass(new CoreEntityPass());
    }

    /**
     * @param array<string, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services.php');

        // make:gm:* generators (dev only dependency).
        if (class_exists(MakerBundle::class)) {
            $container->import('../config/services/maker.php');
        }

        $parameters = $container->parameters();
        $parameters->set('gingerminds_core.admin_prefix', trim((string) $config['admin_prefix'], '/'));
        $parameters->set('gingerminds_core.admin_title', $config['admin_title']);
        $parameters->set('gingerminds_core.admin_title_translation_domain', $config['admin_title_translation_domain']);
        $parameters->set('gingerminds_core.health_check_path', trim((string) $config['health_check_path'], '/'));
        $parameters->set('gingerminds_core.api.prefix', trim((string) $config['api']['prefix'], '/'));
        $parameters->set('gingerminds_core.api.token_ttl', $config['api']['token_ttl']);
        $parameters->set('gingerminds_core.security.authorized_domains', $config['security']['authorized_domains']);
        $parameters->set('gingerminds_core.cache.enabled', $config['cache']['enabled']);
        $parameters->set('gingerminds_core.cache.default_ttl', $config['cache']['default_ttl']);
        $parameters->set('gingerminds_core.permissions', $config['permissions']);
        $parameters->set('gingerminds_core.resources_config', $config['resources']);

        $excludedEntityFiles = [];
        $overriddenEntities = [];

        foreach ($this->coreEntities($config) as $name => $entity) {
            $parameters->set('gingerminds_core.resource.' . $name . '.entity', $entity);
            $default = self::CORE_RESOURCES[$name]['entity'];

            if ($entity !== $default) {
                $overriddenEntities[] = $default;
                $excludedEntityFiles[] = (string) new \ReflectionClass($default)->getFileName();
            }
        }

        $parameters->set('gingerminds_core.excluded_entity_files', $excludedEntityFiles);
        $parameters->set('gingerminds_core.overridden_entities', $overriddenEntities);

        $container->services()
            ->alias('gingerminds_core.cache.pool', (string) $config['cache']['pool']);

        $builder->registerAttributeForAutoconfiguration(
            AsCrudController::class,
            static function (ChildDefinition $definition, AsCrudController $attribute): void {
                $definition->addTag(ResourceRegistryPass::CRUD_CONTROLLER_TAG, array_filter([
                    'resource' => $attribute->resource,
                    'entity' => $attribute->entity,
                    'form' => $attribute->form,
                    'path' => $attribute->path,
                    'permission' => $attribute->permission,
                    'route_prefix' => $attribute->routePrefix,
                    'translation_prefix' => $attribute->translationPrefix,
                    'translation_domain' => $attribute->translationDomain,
                    'template_prefix' => $attribute->templatePrefix,
                ], static fn (?string $value): bool => null !== $value));
                $definition->addTag('controller.service_arguments');
                $definition->setPublic(true);
            },
        );

        $builder->registerAttributeForAutoconfiguration(
            AsFilterHandler::class,
            static function (ChildDefinition $definition, AsFilterHandler $attribute): void {
                $definition->addTag('gingerminds_core.filter_handler', ['type' => $attribute->type]);
            },
        );

        $builder->registerForAutoconfiguration(AdminMenuProviderInterface::class)
            ->addTag('gingerminds_core.admin_menu_provider');
        $builder->registerForAutoconfiguration(LoginResponseEnricherInterface::class)
            ->addTag('gingerminds_core.login_response_enricher');
        $builder->registerForAutoconfiguration(HeaderParameterProviderInterface::class)
            ->addTag('gingerminds_core.api_header_parameter');
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $config = $this->resolveConfig($builder);
        $entities = $this->coreEntities($config);

        $mappings = [];

        foreach (['User', 'Role', 'Permission', 'Security'] as $type) {
            $mappings['GingermindsCore' . $type] = $this->mapping($type);
        }

        $resolveTargetEntities = [];

        foreach (self::CORE_RESOURCES as $name => $resource) {
            $resolveTargetEntities[$resource['interface']] = $entities[$name];
        }

        $builder->prependExtensionConfig('doctrine', [
            'orm' => [
                'resolve_target_entities' => $resolveTargetEntities,
                'mappings' => $mappings,
            ],
        ]);

        $builder->prependExtensionConfig('framework', [
            'rate_limiter' => [
                'gingerminds_core_api_login' => [
                    'policy' => 'fixed_window',
                    'limit' => $config['api']['login_throttling']['max_attempts'],
                    'interval' => $config['api']['login_throttling']['interval'],
                ],
            ],
            'cache' => [
                'pools' => [
                    'gingerminds_core.resource_cache' => [
                        'adapter' => 'cache.app',
                        'tags' => true,
                    ],
                ],
            ],
            'asset_mapper' => [
                'paths' => [$this->getPath() . '/assets' => 'gingerminds-core'],
            ],
        ]);

        if ($builder->hasExtension('symfonycasts_sass')) {
            $projectDir = $builder->getParameter('kernel.project_dir');
            $projectDir = \is_string($projectDir) ? $projectDir : '';
            $rootSass = [$this->getPath() . '/assets/styles/admin.scss'];

            if (is_file($projectDir . '/assets/styles/app.scss')) {
                $rootSass[] = '%kernel.project_dir%/assets/styles/app.scss';
            }

            $builder->prependExtensionConfig('symfonycasts_sass', [
                'root_sass' => $rootSass,
                'sass_options' => [
                    'load_path' => [$this->bootstrapScssParentDirectory($projectDir)],
                    'quiet_deps' => true,
                ],
            ]);
        }
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, class-string>
     */
    private function coreEntities(array $config): array
    {
        $entities = [];

        foreach (self::CORE_RESOURCES as $name => $resource) {
            $entities[$name] = $config['resources'][$name]['entity'] ?? $resource['entity'];
        }

        return $entities;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveConfig(ContainerBuilder $builder): array
    {
        $extension = $this->getContainerExtension();
        $configuration = $extension instanceof ConfigurationExtensionInterface ? $extension->getConfiguration([], $builder) : null;

        if (!$configuration instanceof ConfigurationInterface) {
            throw new \LogicException('The GingermindsCoreBundle configuration cannot be resolved.');
        }

        $configs = $builder->getParameterBag()->resolveValue($builder->getExtensionConfig($this->extensionAlias));

        return new Processor()->processConfiguration($configuration, $configs);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapping(string $directory): array
    {
        return [
            'type' => 'attribute',
            'is_bundle' => false,
            'dir' => $this->getPath() . '/src/Entity/' . $directory,
            'prefix' => 'Gingerminds\\CoreBundle\\Entity\\' . str_replace('/', '\\', $directory),
        ];
    }

    private function bootstrapScssParentDirectory(string $projectDir): string
    {
        foreach ([$projectDir . '/vendor/twbs', $this->getPath() . '/vendor/twbs', \dirname($this->getPath()) . '/twbs'] as $candidate) {
            if (is_dir($candidate . '/bootstrap/scss')) {
                return $candidate;
            }
        }

        return $projectDir . '/vendor/twbs';
    }
}
