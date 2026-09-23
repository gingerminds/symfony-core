<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Tests\Application;

use ApiPlatform\Symfony\Bundle\ApiPlatformBundle;
use DAMA\DoctrineTestBundle\DAMADoctrineTestBundle;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Gingerminds\CoreBundle\GingermindsCoreBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\UX\Autocomplete\AutocompleteBundle;
use Symfony\UX\StimulusBundle\StimulusBundle;
use Symfony\UX\Turbo\TurboBundle;
use Symfonycasts\SassBundle\SymfonycastsSassBundle;
use Twig\Extra\TwigExtraBundle\TwigExtraBundle;

/**
 * Minimal application used by the functional tests and to try the bundle
 * (`APP_ENV=test php tests/Application/bin/console ...`).
 */
final class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->import(__DIR__ . '/config/packages.yaml');
        $container->import(__DIR__ . '/config/services.yaml');

        if ('override' === $this->environment) {
            $container->import(__DIR__ . '/config/override.yaml');
        }
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(__DIR__ . '/config/routes.yaml');
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new SecurityBundle();
        yield new TwigBundle();
        yield new TwigExtraBundle();
        yield new DoctrineBundle();
        yield new ApiPlatformBundle();
        yield new StimulusBundle();
        yield new TurboBundle();
        yield new AutocompleteBundle();
        yield new SymfonycastsSassBundle();
        yield new GingermindsCoreBundle();

        if ('test' === $this->environment) {
            yield new DAMADoctrineTestBundle();
        }
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    public function getCacheDir(): string
    {
        return $this->getVarDir() . '/cache/' . $this->environment;
    }

    public function getLogDir(): string
    {
        return $this->getVarDir() . '/log';
    }

    /**
     * PHPUnit runs in its own directory (GINGERMINDS_VAR_DIR) so it never
     * wipes the database of a manually started test app.
     */
    private function getVarDir(): string
    {
        $dir = $_SERVER['GINGERMINDS_VAR_DIR'] ?? $_ENV['GINGERMINDS_VAR_DIR'] ?? null;

        return \is_string($dir) && '' !== $dir ? $dir : sys_get_temp_dir() . '/gingerminds-core-bundle';
    }
}
