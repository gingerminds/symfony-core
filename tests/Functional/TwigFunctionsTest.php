<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Tests\Functional;

use Gingerminds\CoreBundle\Entity\User\User;
use Gingerminds\CoreBundle\Repository\ListQuery;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
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
        $template = $twig->createTemplate(<<<'TWIG'
            {{ gm_resource('user').name }}|{{ gm_resource_for(entity).name }}|{{ gm_admin_includes('head')|join(',') }}|{{ gm_sort_path('gingerminds_core_user_index', query, 'email') }}|{{ gm_sort_direction(query, 'email') }}
            TWIG);

        self::assertSame(
            'user|user|admin/_head_include.html.twig|/admin/users?sortBy=email&amp;sort=desc|asc',
            $template->render([
                'entity' => User::class,
                'query' => new ListQuery(sortBy: 'email', sort: ListQuery::SORT_ASC),
            ]),
        );
    }
}
