<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\ApiPlatform\Filter;

use ApiPlatform\Doctrine\Orm\Filter\SortFilter;
use ApiPlatform\Metadata\Parameter;
use ApiPlatform\Metadata\Parameters;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\State\ParameterNotFound;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use Gingerminds\CoreBundle\Model\Filter;
use Gingerminds\CoreBundle\Model\SearchableFields;
use Gingerminds\CoreBundle\Model\SortableFields;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * The list parameters of an entity, read from its attributes: one declaration for the API
 * collections (added to their operations by FilterParametersResourceMetadataCollectionFactory)
 * and the admin lists (applied to the repository query by the same API Platform
 * ParameterExtension).
 *
 * - `order[<path>]=asc|desc` for each `#[Sortable]` path (API Platform SortFilter);
 * - `search=text` on the `#[Searchable]` paths (SearchableFilter);
 * - `<property>=...` for each `#[Filter]` property (see Filter for the filters and their values).
 *
 * The parameters carry their validation constraints (sort direction, values of the typed
 * filters): the API answers 422 on an invalid value (API Platform ParameterValidatorProvider),
 * the admin ignores it (discardInvalid()).
 *
 * @phpstan-type PanelField array{
 *     type: string,
 *     label: ?string,
 *     choices: array<int|string, string>,
 *     multiple: bool,
 *     entity: ?class-string,
 *     resource: ?string,
 *     template: ?string,
 * }
 */
final class FilterParameters
{
    public const string SEARCH = 'search';
    public const string ORDER = 'order';
    public const string SEARCH_PROPERTY = 'hydra:freetextQuery';

    /**
     * Filter service ids (`api_platform.filter` tag) of the core filters needing services.
     */
    public const string BOOLEAN_FILTER = 'gingerminds_core.api.filter.boolean';
    public const string DATE_FILTER = 'gingerminds_core.api.filter.date';
    public const string ENUM_FILTER = 'gingerminds_core.api.filter.enum';
    public const string RELATION_FILTER = 'gingerminds_core.api.filter.relation';
    public const string SEARCH_FILTER = 'gingerminds_core.api.filter.search';

    /** @var array<class-string, array{parameters: list<QueryParameter>, panel: array<string, PanelField>}> */
    private array $definitions = [];

    public function __construct(
        private readonly ManagerRegistry $registry,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /**
     * The list parameters of the entity, without values.
     *
     * @param class-string $entityClass
     */
    public function create(string $entityClass): Parameters
    {
        return new Parameters(array_map(
            static fn (QueryParameter $parameter): QueryParameter => clone $parameter,
            $this->definition($entityClass)['parameters'],
        ));
    }

    /**
     * The list parameters of the entity valued from a query string (`$request->query->all()`),
     * read as API Platform reads them (`order[name]`, `publishedAt[after]`). Empty values (an
     * empty field of the admin panel) are not values.
     *
     * @param class-string         $entityClass
     * @param array<string, mixed> $query
     */
    public function fromQuery(string $entityClass, array $query): Parameters
    {
        $parameters = $this->create($entityClass);

        foreach ($parameters as $key => $parameter) {
            $parameter->setValue($this->extract($key, $query));
        }

        return $parameters;
    }

    /**
     * Validates the valued parameters against their constraints, as the API does: the invalid
     * ones lose their value (the admin lists ignore them).
     *
     * @return array<string, string> the message of each discarded parameter, by key
     */
    public function discardInvalid(Parameters $parameters): array
    {
        $errors = [];

        foreach ($parameters as $key => $parameter) {
            $value = $parameter->getValue(null);
            $constraints = $parameter->getConstraints();

            if (null === $value || $value instanceof ParameterNotFound || null === $constraints || [] === $constraints) {
                continue;
            }

            $violations = $this->validator->validate($value, $constraints);

            if (\count($violations) > 0) {
                $errors[$key] = (string) $violations->get(0)->getMessage();
                $parameter->setValue(new ParameterNotFound());
            }
        }

        return $errors;
    }

    /**
     * The fields of the admin filters panel, by query parameter (`#[Filter]` but `admin: false`).
     *
     * @param class-string $entityClass
     *
     * @return array<string, PanelField>
     */
    public function panel(string $entityClass): array
    {
        return $this->definition($entityClass)['panel'];
    }

    /**
     * The value of a parameter, null when it has none.
     */
    public static function value(Parameters $parameters, string $key): mixed
    {
        $value = $parameters->get($key)?->getValue(null);

        return $value instanceof ParameterNotFound ? null : $value;
    }

    /**
     * The parameters having a value, sorts aside: the active filters, by key.
     *
     * @return array<string, mixed>
     */
    public static function filterValues(Parameters $parameters): array
    {
        $values = [];

        foreach ($parameters as $key => $parameter) {
            $value = $parameter->getValue(null);

            if (null !== $value && !$value instanceof ParameterNotFound && !$parameter->getFilter() instanceof SortFilter) {
                $values[$key] = $value;
            }
        }

        return $values;
    }

    /**
     * The parameters but those of $keys and the sorts (a count, a facet).
     *
     * @param list<string> $keys
     */
    public static function without(Parameters $parameters, array $keys = []): Parameters
    {
        $kept = [];

        foreach ($parameters as $key => $parameter) {
            if (!\in_array($key, $keys, true) && !$parameter->getFilter() instanceof SortFilter) {
                $kept[] = $parameter;
            }
        }

        return new Parameters($kept);
    }

    /**
     * @param class-string $entityClass
     *
     * @return array{parameters: list<QueryParameter>, panel: array<string, PanelField>}
     */
    private function definition(string $entityClass): array
    {
        if (isset($this->definitions[$entityClass])) {
            return $this->definitions[$entityClass];
        }

        $metadata = $this->metadata($entityClass);
        $parameters = $this->sortParameters($entityClass, $metadata);
        $panel = [];

        if ([] !== ($paths = SearchableFields::of($entityClass))) {
            // `property`: the Hydra term of a free text search, for the Hydra `search` template
            // (SearchableFilter searches the `properties`).
            $parameters[] = new QueryParameter(key: self::SEARCH, filter: self::SEARCH_FILTER, property: self::SEARCH_PROPERTY, properties: $paths);
        }

        foreach ($this->filterAttributes($entityClass) as $name => $filter) {
            if (\in_array($name, array_map(static fn (Parameter $parameter): ?string => $parameter->getKey(), $parameters), true)) {
                continue;
            }

            $type = $filter->type ?? FilterTypes::guess($metadata, $name);
            $parameters[] = new QueryParameter(key: $name, filter: FilterTypes::filter($type), property: $name, constraints: FilterTypes::constraints($metadata, $name, $type));

            if ($filter->admin) {
                $panel[$name] = FilterTypes::panelField($metadata, $name, $type, $filter);
            }
        }

        return $this->definitions[$entityClass] = ['parameters' => $parameters, 'panel' => $panel];
    }

    /**
     * An `order[<path>]` parameter per `#[Sortable]` path.
     *
     * @param class-string               $entityClass
     * @param ClassMetadata<object>|null $metadata
     *
     * @return list<QueryParameter>
     */
    private function sortParameters(string $entityClass, ?ClassMetadata $metadata): array
    {
        $parameters = [];

        foreach (SortableFields::of($entityClass) as $path) {
            $parameters[] = new QueryParameter(
                key: self::ORDER . '[' . $path . ']',
                filter: new SortFilter(),
                property: $path,
                description: 'Sort by ' . $path . ': asc or desc',
                constraints: [new Choice(choices: ['asc', 'desc', 'ASC', 'DESC'])],
                extraProperties: $this->nestedPropertyInfo($metadata, $path),
            );
        }

        return $parameters;
    }

    /**
     * The `#[Filter]` of each property, parent classes included: the first declared one wins.
     *
     * @param class-string $entityClass
     *
     * @return array<string, Filter>
     */
    private function filterAttributes(string $entityClass): array
    {
        $filters = [];

        for ($reflection = new \ReflectionClass($entityClass); false !== $reflection; $reflection = $reflection->getParentClass()) {
            foreach ($reflection->getProperties() as $property) {
                foreach ($property->getAttributes(Filter::class) as $attribute) {
                    $filters[$property->getName()] ??= $attribute->newInstance();
                }
            }
        }

        return $filters;
    }

    /**
     * The `nested_properties_info` API Platform's filters join a path through (SortFilter): read
     * from the Doctrine mapping, so that the admin, whose parameters are not built by the API
     * Platform metadata factories, joins it too.
     *
     * @param ClassMetadata<object>|null $metadata
     *
     * @return array<string, mixed>
     */
    private function nestedPropertyInfo(?ClassMetadata $metadata, string $path): array
    {
        $segments = explode('.', $path);
        $leaf = array_pop($segments);
        $classes = [];

        if ([] === $segments || !$metadata instanceof ClassMetadata) {
            return [];
        }

        foreach ($segments as $association) {
            $target = $metadata->hasAssociation($association) ? $this->metadata($metadata->getAssociationTargetClass($association)) : null;

            if (!$target instanceof ClassMetadata) {
                return [];
            }

            $classes[] = $metadata->getName();
            $metadata = $target;
        }

        return ['nested_properties_info' => [$path => [
            'relation_segments' => $segments,
            'converted_relation_segments' => $segments,
            'relation_classes' => $classes,
            'leaf_property' => $leaf,
            'leaf_class' => $metadata->getName(),
        ]]];
    }

    /**
     * @param class-string $class
     *
     * @return ClassMetadata<object>|null
     */
    private function metadata(string $class): ?ClassMetadata
    {
        $manager = $this->registry->getManagerForClass($class);

        return $manager instanceof EntityManagerInterface ? $manager->getClassMetadata($class) : null;
    }

    /**
     * The value of a parameter key in a query string array, `ParameterNotFound` when absent or empty.
     *
     * @param array<string, mixed> $query
     */
    private function extract(string $key, array $query): mixed
    {
        preg_match_all('/[^\[\]]+/', $key, $matches);
        $value = $query;

        foreach ($matches[0] as $segment) {
            if (!\is_array($value) || !\array_key_exists($segment, $value)) {
                return new ParameterNotFound();
            }

            $value = $value[$segment];
        }

        $value = self::withoutEmpty($value);

        return $value ?? new ParameterNotFound();
    }

    private static function withoutEmpty(mixed $value): mixed
    {
        if (!\is_array($value)) {
            return \is_string($value) && '' === trim($value) ? null : $value;
        }

        $isList = array_is_list($value);
        $value = array_filter(array_map(self::withoutEmpty(...), $value), static fn (mixed $item): bool => null !== $item);

        if ([] === $value) {
            return null;
        }

        // A list stays a list (`status[]=`): API Platform reads an associative array as operators.
        return $isList ? array_values($value) : $value;
    }
}
