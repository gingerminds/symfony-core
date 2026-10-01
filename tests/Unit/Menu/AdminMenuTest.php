<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Tests\Unit\Menu;

use Gingerminds\CoreBundle\Menu\AdminMenu;
use Gingerminds\CoreBundle\Menu\AdminMenuProviderInterface;
use Gingerminds\CoreBundle\Menu\MenuItem;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class AdminMenuTest extends TestCase
{
    public function testSortsByAscendingWeightKeepingTheRegistrationOrderOnATie(): void
    {
        $menu = $this->menu([
            [new MenuItem('last', 'last', weight: 50), new MenuItem('first', 'first', weight: -10)],
            [new MenuItem('tie a', 'tie_a'), new MenuItem('tie b', 'tie_b')],
        ]);

        self::assertSame(['first', 'tie a', 'tie b', 'last'], $this->labels($menu->getItems()));
    }

    public function testMergesTheSectionsSharingAnId(): void
    {
        $menu = $this->menu([
            [new MenuItem('Administration', icon: 'bi-gear', children: [
                new MenuItem('users', 'users', weight: 0),
                new MenuItem('roles', 'roles', weight: 20),
            ], id: 'administration', weight: 100)],
            [new MenuItem('Ignored label', icon: 'bi-ignored', children: [
                new MenuItem('sites', 'sites', weight: 40),
                new MenuItem('contributors', 'contributors', weight: 10),
            ], id: 'administration')],
        ]);

        $items = $menu->getItems();

        self::assertCount(1, $items);
        self::assertSame('Administration', $items[0]->label);
        self::assertSame('bi-gear', $items[0]->icon);
        self::assertSame(100, $items[0]->weight);
        self::assertSame(['users', 'contributors', 'roles', 'sites'], $this->labels($items[0]->children));
    }

    public function testDropsTheEntriesNotGrantedAndTheEmptySections(): void
    {
        $menu = $this->menu([
            [new MenuItem('section', children: [new MenuItem('denied', 'denied', permission: 'DENIED')], id: 'section')],
            [new MenuItem('visible', 'visible')],
        ]);

        self::assertSame(['visible'], $this->labels($menu->getItems()));
    }

    public function testDeprecatedPriorityIsTheOppositeWeight(): void
    {
        $deprecations = [];
        set_error_handler(static function (int $level, string $message) use (&$deprecations): bool {
            $deprecations[] = $message;

            return true;
        }, \E_USER_DEPRECATED);

        try {
            $item = new MenuItem('legacy', 'legacy', priority: 1000);
        } finally {
            restore_error_handler();
        }

        self::assertSame(-1000, $item->weight);
        self::assertCount(1, $deprecations);
        self::assertStringContainsString('"priority" argument', $deprecations[0]);
        self::assertSame(5, new MenuItem('weighted', 'weighted', priority: 0, weight: 5)->weight);
    }

    /**
     * @param list<list<MenuItem>> $providers items of each provider
     */
    private function menu(array $providers): AdminMenu
    {
        $checker = $this->createStub(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->willReturnCallback(static fn (mixed $attribute): bool => 'DENIED' !== $attribute);

        return new AdminMenu(
            array_map(static fn (array $items): AdminMenuProviderInterface => new readonly class($items) implements AdminMenuProviderInterface {
                /**
                 * @param list<MenuItem> $items
                 */
                public function __construct(private array $items)
                {
                }

                public function getItems(): iterable
                {
                    return $this->items;
                }
            }, $providers),
            $checker,
        );
    }

    /**
     * @param list<MenuItem> $items
     *
     * @return list<string>
     */
    private function labels(array $items): array
    {
        return array_map(static fn (MenuItem $item): string => $item->label, $items);
    }
}
