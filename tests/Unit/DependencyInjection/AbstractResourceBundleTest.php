<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Tests\Unit\DependencyInjection;

use Gingerminds\CoreBundle\Bundle\AbstractResourceBundle;
use Gingerminds\CoreBundle\DependencyInjection\Compiler\RepositoryParameterExtensionPass;
use Gingerminds\CoreBundle\Repository\User\UserRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;

final class AbstractResourceBundleTest extends TestCase
{
    public function testExtensionConfigValueIsTheLastNonEmptyOne(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new class extends Extension {
            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            public function getAlias(): string
            {
                return 'gingerminds_multisite';
            }
        });
        $container->prependExtensionConfig('gingerminds_multisite', ['translation' => ['log_channel' => 'google']]);
        $container->loadFromExtension('gingerminds_multisite', ['translation' => ['enabled' => true]]);
        $container->loadFromExtension('gingerminds_multisite', ['translation' => ['log_channel' => 'translation']]);
        $container->loadFromExtension('gingerminds_multisite', ['translation' => ['log_channel' => '']]);

        self::assertSame('translation', AbstractResourceBundle::extensionConfigValue($container, 'gingerminds_multisite', 'translation.log_channel', 'default'));
        self::assertSame('default', AbstractResourceBundle::extensionConfigValue($container, 'gingerminds_multisite', 'translation.cache_ttl', 'default'));
        self::assertNull(AbstractResourceBundle::extensionConfigValue($container, 'gingerminds_cms', 'translation.log_channel'));
    }

    public function testEveryRepositoryGetsTheParameterExtension(): void
    {
        $container = new ContainerBuilder();
        $container->register('api_platform.doctrine.orm.extension.parameter_extension', \stdClass::class);
        $container->register(UserRepository::class, UserRepository::class);
        $container->register('app.other', \stdClass::class);
        $container->register('app.configured', UserRepository::class)->addMethodCall('setParameterExtension', [new Reference('app.extension')]);

        new RepositoryParameterExtensionPass()->process($container);

        self::assertTrue($container->getDefinition(UserRepository::class)->hasMethodCall('setParameterExtension'));
        self::assertFalse($container->getDefinition('app.other')->hasMethodCall('setParameterExtension'));
        self::assertCount(1, $container->getDefinition('app.configured')->getMethodCalls());
    }
}
