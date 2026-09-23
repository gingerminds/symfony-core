<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Tests\Unit\Twig;

use Doctrine\Persistence\ManagerRegistry;
use Gingerminds\CoreBundle\Menu\AdminMenu;
use Gingerminds\CoreBundle\Resource\ResourceRegistry;
use Gingerminds\CoreBundle\Twig\GingermindsCoreExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;

final class AdminTitleTest extends TestCase
{
    public function testAdminTitleIsTranslatedInItsDomain(): void
    {
        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['backoffice.title' => 'Back office'], 'en', 'admin');

        self::assertSame('Back office', $this->title($translator, 'backoffice.title', 'admin'));
        self::assertSame('Plain title', $this->title($translator, 'Plain title', 'admin'));
    }

    private function title(Translator $translator, string $title, string $domain): string
    {
        $extension = new GingermindsCoreExtension(
            new ResourceRegistry(),
            new AdminMenu([], $this->createStub(AuthorizationCheckerInterface::class)),
            $this->createStub(UrlGeneratorInterface::class),
            new RequestStack(),
            $this->createStub(ManagerRegistry::class),
            $translator,
            $title,
            $domain,
        );

        foreach ($extension->getFunctions() as $function) {
            if ('gm_admin_title' === $function->getName()) {
                return ($function->getCallable())();
            }
        }

        self::fail('gm_admin_title() is not registered.');
    }
}
