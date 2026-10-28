<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Tests\Unit\Twig;

use Gingerminds\CoreBundle\Menu\AdminMenu;
use Gingerminds\CoreBundle\Twig\AdminExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\RequestStack;
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
        $extension = new AdminExtension(
            new AdminMenu([], $this->createStub(AuthorizationCheckerInterface::class), new EventDispatcher()),
            new RequestStack(),
            $translator,
            $title,
            $domain,
        );

        return $extension->adminTitle();
    }
}
