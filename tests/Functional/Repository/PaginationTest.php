<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Tests\Functional\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\PersistentCollection;
use Gingerminds\CoreBundle\Entity\Role\Role;
use Gingerminds\CoreBundle\Entity\User\Contributor;
use Gingerminds\CoreBundle\Entity\User\User;
use Gingerminds\CoreBundle\Repository\ListQuery;
use Gingerminds\CoreBundle\Tests\Application\Entity\Category;
use Gingerminds\CoreBundle\Tests\Application\Entity\Product;
use Gingerminds\CoreBundle\Tests\Application\Repository\ProductRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * AbstractRepository::paginate(): items and total, with and without a collection join
 * (DISTINCT subqueries / COUNT(DISTINCT) only then).
 */
final class PaginationTest extends KernelTestCase
{
    private ProductRepository $products;

    /** @var array<string, Category> */
    private array $tags = [];

    protected function setUp(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $category = new Category('Category');
        $entityManager->persist($category);

        foreach (['red', 'blue'] as $name) {
            $entityManager->persist($this->tags[$name] = new Category($name));
        }

        // 5 products; the first three carry both tags (2 joined rows each).
        foreach (['A', 'B', 'C', 'D', 'E'] as $i => $name) {
            $product = new Product()->setName($name)->setCategory($category);

            if ($i < 3) {
                $product->addTag($this->tags['red'])->addTag($this->tags['blue']);
            }

            $entityManager->persist($product);
        }

        $entityManager->flush();
        $entityManager->clear();

        $this->products = $entityManager->getRepository(Product::class);
    }

    /**
     * @return iterable<string, array{int, list<string>}>
     */
    public static function pages(): iterable
    {
        yield 'full page (counted)' => [1, ['A', 'B']];
        yield 'middle page (counted)' => [2, ['C', 'D']];
        yield 'partial last page (total from the offset)' => [3, ['E']];
        yield 'past the end (counted)' => [9, []];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('pages')]
    public function testPagesWithToOneEagerLoad(int $page, array $expected): void
    {
        $items = $this->products->paginate(new ListQuery(page: $page, itemsPerPage: 2, sortBy: 'name'));

        self::assertSame($expected, array_map(static fn (Product $product): ?string => $product->getName(), $items->getItems()));
        self::assertSame(5, $items->getTotalItems());
        self::assertSame(3, $items->getLastPage());
    }

    public function testCollectionEagerLoadsAreLoadedForThePage(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $roles = [];

        foreach (['First', 'Second'] as $name) {
            $roles[] = $role = new Role();
            $role->setName($name);
            $entityManager->persist($role);
        }

        foreach (['u1', 'u2', 'u3'] as $name) {
            $user = new User();
            $user->setEmail($name . '@example.com');
            $user->setPassword('x');
            array_map($user->addRoleEntity(...), $roles);
            $contributor = new Contributor();
            $contributor->setFirstname('First');
            $contributor->setLastname($name);
            $contributor->setUser($user);
            $entityManager->persist($user);
            $entityManager->persist($contributor);
        }

        $entityManager->flush();
        $entityManager->clear();

        // Full page of users having 2 roles each: no duplicate, order kept, roles loaded.
        $items = $entityManager->getRepository(User::class)->paginate(new ListQuery(itemsPerPage: 2, sortBy: 'email'));

        self::assertSame(['u1@example.com', 'u2@example.com'], array_map(static fn (User $user): string => $user->getEmail(), $items->getItems()));
        self::assertSame(3, $items->getTotalItems());

        foreach ($items as $user) {
            $roleEntities = $user->getRoleEntities();
            self::assertInstanceOf(PersistentCollection::class, $roleEntities);
            self::assertTrue($roleEntities->isInitialized());
            self::assertCount(2, $roleEntities);
            self::assertSame($user->getEmail(), $user->getContributor()?->getLastname() . '@example.com');
        }
    }

    public function testCollectionFilterCountsEachEntityOnce(): void
    {
        $filters = ['tags' => [(string) $this->tags['red']->getId(), (string) $this->tags['blue']->getId()]];

        $items = $this->products->paginate(new ListQuery(page: 1, itemsPerPage: 2, sortBy: 'name', filters: $filters));

        self::assertSame(['A', 'B'], array_map(static fn (Product $product): ?string => $product->getName(), $items->getItems()));
        self::assertSame(3, $items->getTotalItems());
    }
}
