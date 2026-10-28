<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Twig;

use Gingerminds\CoreBundle\Menu\AdminMenu;
use Gingerminds\CoreBundle\Menu\MenuItem;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Attribute\AsTwigFunction;

/**
 * Twig functions of the admin layout (`gm_admin_menu()`, `gm_admin_title()`, `gm_admin_includes()`,
 * `gm_form_includes()`, `gm_is_menu_active()`): a Twig runtime, instantiated on the first call only.
 */
final readonly class AdminExtension
{
    private const array FORM_COLUMNS = ['main', 'sidebar'];

    public function __construct(
        private AdminMenu $menu,
        private RequestStack $requestStack,
        private TranslatorInterface $translator,
        private string $adminTitle,
        private string $adminTitleDomain,
        /** @var array<string, list<string>> */
        private array $adminIncludes = [],
    ) {
    }

    /**
     * @return list<MenuItem>
     */
    #[AsTwigFunction('gm_admin_menu')]
    public function adminMenu(): array
    {
        return $this->menu->getItems();
    }

    #[AsTwigFunction('gm_admin_title')]
    public function adminTitle(): string
    {
        return $this->translator->trans($this->adminTitle, [], $this->adminTitleDomain);
    }

    /**
     * Templates included in an admin layout slot (`gingerminds_core.admin_includes`).
     *
     * @return list<string>
     */
    #[AsTwigFunction('gm_admin_includes')]
    public function adminIncludes(string $slot): array
    {
        return $this->adminIncludes[$slot] ?? [];
    }

    /**
     * Templates included in a form column: the `<name>_form_main` / `<name>_form_sidebar` admin include slots.
     *
     * @return list<string>
     */
    #[AsTwigFunction('gm_form_includes')]
    public function formIncludes(string $name, string $column): array
    {
        if (!\in_array($column, self::FORM_COLUMNS, true)) {
            throw new \InvalidArgumentException(\sprintf('Unknown form column "%s", expected one of "%s".', $column, implode('", "', self::FORM_COLUMNS)));
        }

        return $this->adminIncludes($name . '_form_' . $column);
    }

    #[AsTwigFunction('gm_is_menu_active')]
    public function isMenuActive(MenuItem $item): bool
    {
        $current = (string) $this->requestStack->getCurrentRequest()?->attributes->get('_route');
        $prefix = $item->getActivePrefix();

        if (null !== $prefix && ($current === $item->route || 1 === preg_match('/^' . preg_quote($prefix, '/') . '_(index|new|edit|delete)$/', $current))) {
            return true;
        }

        return array_any($item->children, fn (MenuItem $child): bool => $this->isMenuActive($child));
    }
}
