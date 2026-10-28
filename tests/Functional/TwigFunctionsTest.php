<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Tests\Functional;

use Gingerminds\CoreBundle\Entity\User\User;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;

/**
 * The `gm_*` Twig functions, declared with #[AsTwigFunction] on runtime services, as a
 * template calls them.
 */
final class TwigFunctionsTest extends KernelTestCase
{
    public function testEveryFunctionIsRegistered(): void
    {
        $twig = self::getContainer()->get(Environment::class);

        foreach (['gm_resource', 'gm_resource_for', 'gm_entity_choices', 'gm_admin_menu', 'gm_admin_title', 'gm_admin_includes', 'gm_form_includes', 'gm_is_menu_active', 'gm_list_path', 'gm_sort_path', 'gm_sort_direction'] as $name) {
            self::assertNotNull($twig->getFunction($name), $name);
        }
    }

    public function testFunctionsRenderThroughTheirRuntimes(): void
    {
        $twig = self::getContainer()->get(Environment::class);
        self::getContainer()->get(RequestStack::class)->push(Request::create('/admin/users', 'GET', [
            'order' => ['email' => 'asc'],
            'search' => 'john',
            'status' => '',
            'page' => '3',
        ]));
        $template = $twig->createTemplate(<<<'TWIG'
            {{ gm_resource('user').name }}|{{ gm_resource_for(entity).name }}|{{ gm_admin_includes('head')|join(',') }}|{{ gm_sort_path('gingerminds_core_user_index', 'email') }}|{{ gm_sort_direction('email') }}|{{ gm_list_path('gingerminds_core_user_index', {page: 4}) }}
            TWIG);

        // The sort toggles, keeps the other parameters, drops the page and the empty values.
        self::assertSame(
            'user|user|admin/_head_include.html.twig|/admin/users?order%5Bemail%5D=desc&amp;search=john|asc|/admin/users?order%5Bemail%5D=asc&amp;search=john&amp;page=4',
            $template->render(['entity' => User::class]),
        );
    }
}
