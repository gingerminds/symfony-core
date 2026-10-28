<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Tests\Unit\Menu;

use Gingerminds\CoreBundle\Menu\AdminMenu;
use Gingerminds\CoreBundle\Menu\AdminMenuBuiltEvent;
use Gingerminds\CoreBundle\Menu\AdminMenuProviderInterface;
use Gingerminds\CoreBundle\Menu\MenuItem;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
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

    public function testTheBuiltEventRemovesReplacesAndAddsEntriesAtAnyLevel(): void
    {
        $menu = $this->menu([
            [
                new MenuItem('Dashboard', 'dashboard', id: 'dashboard'),
                new MenuItem('Administration', children: [
                    new MenuItem('users', 'users', id: 'user', weight: 0),
                    new MenuItem('permissions', 'permissions', id: 'permission', weight: 30),
                ], id: 'administration', weight: 100),
            ],
        ], static function (AdminMenuBuiltEvent $event): void {
            self::assertTrue($event->remove('permission'));
            self::assertFalse($event->remove('unknown'));
            self::assertTrue($event->replace('user', static fn (MenuItem $item): MenuItem => $item->with(label: 'accounts', icon: null, weight: 50)));
            $event->replace('dashboard', new MenuItem('Home', 'home', id: 'dashboard', weight: 200));
            // Sorted and filtered like the provider entries.
            $event->add(new MenuItem('settings', 'settings', weight: 10), 'administration');
            $event->add(new MenuItem('denied', 'denied', permission: 'DENIED'), 'administration');
        });

        $items = $menu->getItems();

        self::assertSame(['Administration', 'Home'], $this->labels($items));
        self::assertSame(['settings', 'accounts'], $this->labels($items[0]->children));
        self::assertNull($items[0]->children[1]->icon);
        self::assertSame('users', $items[0]->children[1]->route);
    }

    public function testTheBuiltEventReceivesTheMergedEntries(): void
    {
        $menu = $this->menu([
            [new MenuItem('Administration', children: [new MenuItem('users', 'users', id: 'user')], id: 'administration')],
            [new MenuItem('Ignored', children: [new MenuItem('sites', 'sites', id: 'site')], id: 'administration')],
        ], static function (AdminMenuBuiltEvent $event): void {
            self::assertCount(1, $event->getItems());
            self::assertSame('sites', $event->get('site')?->label);
            self::assertNull($event->get('unknown'));
        });

        self::assertSame(['users', 'sites'], $this->labels($menu->getItems()[0]->children));
    }

    public function testAddingUnderAnUnknownEntryFails(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The admin menu has no entry "unknown".');

        $this->menu([[]], static fn (AdminMenuBuiltEvent $event) => $event->add(new MenuItem('x', 'x'), 'unknown'))->getItems();
    }

    public function testWithKeepsTheOtherArguments(): void
    {
        $item = new MenuItem('label', 'route', ['id' => 1], 'bi-icon', 'VIEW', 'user', translationDomain: 'admin', id: 'entry', weight: 5);
        $copy = $item->with(label: 'other');

        self::assertNotSame($item, $copy);
        self::assertSame('label', $item->label);
        self::assertSame(['other', 'route', ['id' => 1], 'bi-icon', 'VIEW', 'user', 'admin', 'entry', 5], [
            $copy->label, $copy->route, $copy->routeParameters, $copy->icon, $copy->permission, $copy->permissionSubject,
            $copy->translationDomain, $copy->id, $copy->weight,
        ]);
    }

    /**
     * @param list<list<MenuItem>>                        $providers items of each provider
     * @param (\Closure(AdminMenuBuiltEvent): mixed)|null $listener
     */
    private function menu(array $providers, ?\Closure $listener = null): AdminMenu
    {
        $dispatcher = new EventDispatcher();

        if ($listener instanceof \Closure) {
            $dispatcher->addListener(AdminMenuBuiltEvent::class, $listener);
        }

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
            $dispatcher,
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
