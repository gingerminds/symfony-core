<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Twig;

use Doctrine\Persistence\ManagerRegistry;
use Gingerminds\CoreBundle\Menu\AdminMenu;
use Gingerminds\CoreBundle\Menu\MenuItem;
use Gingerminds\CoreBundle\Repository\ListQuery;
use Gingerminds\CoreBundle\Resource\ResourceDefinition;
use Gingerminds\CoreBundle\Resource\ResourceRegistry;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class GingermindsCoreExtension extends AbstractExtension
{
    private const int MAX_LOCAL_CHOICES = 500;

    public function __construct(
        private readonly ResourceRegistry $resources,
        private readonly AdminMenu $menu,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly RequestStack $requestStack,
        private readonly ManagerRegistry $doctrine,
        private readonly TranslatorInterface $translator,
        private readonly string $adminTitle,
        private readonly string $adminTitleDomain,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('gm_resource', $this->resource(...)),
            new TwigFunction('gm_resource_for', $this->resourceFor(...)),
            new TwigFunction('gm_entity_choices', $this->entityChoices(...)),
            new TwigFunction('gm_admin_menu', $this->menu->getItems(...)),
            new TwigFunction('gm_admin_title', fn (): string => $this->translator->trans($this->adminTitle, [], $this->adminTitleDomain)),
            new TwigFunction('gm_is_menu_active', $this->isMenuActive(...)),
            new TwigFunction('gm_list_path', $this->listPath(...)),
            new TwigFunction('gm_sort_path', $this->sortPath(...)),
            new TwigFunction('gm_sort_direction', $this->sortDirection(...)),
        ];
    }

    public function resource(string $name): ResourceDefinition
    {
        return $this->resources->get($name);
    }

    public function resourceFor(string $entityClass): ?ResourceDefinition
    {
        return $this->resources->findByEntity($entityClass);
    }

    /**
     * @param list<int|string>|int|string|null $ids
     *
     * @return list<array{value: string, text: string}>
     */
    public function entityChoices(string $entityClass, array|int|string|null $ids = null): array
    {
        if (!class_exists($entityClass)) {
            return [];
        }

        $repository = $this->doctrine->getRepository($entityClass);

        if (null !== $ids) {
            $ids = array_values(array_filter((array) $ids, static fn (mixed $id): bool => '' !== $id && 'all' !== $id));

            if ([] === $ids) {
                return [];
            }

            $entities = $repository->findBy(['id' => $ids]);
        } else {
            $entities = $repository->findBy([], null, self::MAX_LOCAL_CHOICES);
        }

        $choices = [];

        foreach ($entities as $entity) {
            $id = method_exists($entity, 'getId') ? (string) $entity->getId() : '';
            $choices[] = ['value' => $id, 'text' => $entity instanceof \Stringable ? (string) $entity : '#' . $id];
        }

        return $choices;
    }

    public function isMenuActive(MenuItem $item): bool
    {
        $current = (string) $this->requestStack->getCurrentRequest()?->attributes->get('_route');
        $prefix = $item->getActivePrefix();

        if (null !== $prefix && ($current === $item->route || 1 === preg_match('/^' . preg_quote($prefix, '/') . '_(index|new|edit|delete)$/', $current))) {
            return true;
        }

        return array_any($item->children, fn (MenuItem $child): bool => $this->isMenuActive($child));
    }

    /**
     * @param array<string, mixed> $overrides
     * @param array<string, mixed> $routeParameters
     */
    public function listPath(string $route, ListQuery $query, array $overrides = [], array $routeParameters = []): string
    {
        $parameters = array_filter(
            [...$query->toQueryParameters(), ...$overrides],
            static fn (mixed $value): bool => null !== $value && '' !== $value,
        );

        return $this->urlGenerator->generate($route, [...$routeParameters, ...$parameters]);
    }

    /**
     * @param array<string, mixed> $routeParameters
     */
    public function sortPath(string $route, ListQuery $query, string $property, array $routeParameters = []): string
    {
        $sort = $query->sortBy === $property && ListQuery::SORT_ASC === $query->sort ? ListQuery::SORT_DESC : ListQuery::SORT_ASC;

        return $this->listPath($route, $query, ['sortBy' => $property, 'sort' => $sort, 'page' => null], $routeParameters);
    }

    public function sortDirection(ListQuery $query, string $property): ?string
    {
        return $query->sortBy === $property ? $query->sort : null;
    }
}
