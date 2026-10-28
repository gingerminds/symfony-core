<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\ApiPlatform\Filter;

use ApiPlatform\Doctrine\Orm\Filter\ComparisonFilter;
use ApiPlatform\Doctrine\Orm\Filter\ExactFilter;
use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Filter\PartialSearchFilter;
use ApiPlatform\Doctrine\Orm\Filter\SortFilter;
use ApiPlatform\Metadata\Parameter;
use ApiPlatform\Metadata\Parameters;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\State\ParameterNotFound;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use Gingerminds\CoreBundle\ApiPlatform\Filter\Constraint\ParameterValue;
use Gingerminds\CoreBundle\Model\Filter;
use Gingerminds\CoreBundle\Model\SearchableFields;
use Gingerminds\CoreBundle\Model\SortableFields;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Date;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Type;
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

    /**
     * Filter service ids (`api_platform.filter` tag) of the core filters needing services.
     */
    public const string BOOLEAN_FILTER = 'gingerminds_core.api.filter.boolean';
    public const string DATE_FILTER = 'gingerminds_core.api.filter.date';
    public const string ENUM_FILTER = 'gingerminds_core.api.filter.enum';
    public const string RELATION_FILTER = 'gingerminds_core.api.filter.relation';
    public const string SEARCH_FILTER = 'gingerminds_core.api.filter.search';

    /**
     * Admin panel field (`templates/components/list/filter/_<type>.html.twig`) of each filter type.
     */
    private const array PANEL_TYPES = [
        Filter::EXACT => 'select',
        Filter::PARTIAL => 'text',
        Filter::NUMBER => 'number',
        Filter::DATE => 'date',
        Filter::BOOLEAN => 'boolean',
        Filter::ENUM => 'select',
        Filter::RELATION => 'entity',
    ];

    private const array NUMBER_TYPES = [Types::INTEGER, Types::SMALLINT, Types::BIGINT, Types::FLOAT, Types::DECIMAL, 'smallfloat'];

    private const array DATE_TYPES = [
        Types::DATE_MUTABLE, Types::DATE_IMMUTABLE, Types::DATETIME_MUTABLE, Types::DATETIME_IMMUTABLE,
        Types::DATETIMETZ_MUTABLE, Types::DATETIMETZ_IMMUTABLE, 'day_point',
    ];

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
        $parameters = [];
        $panel = [];

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

        if ([] !== ($paths = SearchableFields::of($entityClass))) {
            $parameters[] = new QueryParameter(key: self::SEARCH, filter: self::SEARCH_FILTER, properties: $paths);
        }

        for ($reflection = new \ReflectionClass($entityClass); false !== $reflection; $reflection = $reflection->getParentClass()) {
            foreach ($reflection->getProperties() as $property) {
                $name = $property->getName();

                foreach ($property->getAttributes(Filter::class) as $attribute) {
                    if (isset($panel[$name]) || \in_array($name, array_map(static fn (Parameter $parameter): ?string => $parameter->getKey(), $parameters), true)) {
                        continue;
                    }

                    $filter = $attribute->newInstance();
                    $type = $filter->type ?? $this->guessType($metadata, $name);
                    $parameters[] = new QueryParameter(key: $name, filter: $this->filter($type), property: $name, constraints: $this->constraints($metadata, $name, $type));

                    if ($filter->admin) {
                        $panel[$name] = $this->panelField($metadata, $name, $type, $filter);
                    }
                }
            }
        }

        return $this->definitions[$entityClass] = ['parameters' => $parameters, 'panel' => $panel];
    }

    /**
     * @param ClassMetadata<object>|null $metadata
     */
    private function guessType(?ClassMetadata $metadata, string $property): string
    {
        if (!$metadata instanceof ClassMetadata) {
            return Filter::EXACT;
        }

        if ($metadata->hasAssociation($property)) {
            return Filter::RELATION;
        }

        if (!$metadata->hasField($property)) {
            return Filter::EXACT;
        }

        $type = $metadata->getTypeOfField($property);

        return match (true) {
            null !== $metadata->getFieldMapping($property)->enumType => Filter::ENUM,
            Types::BOOLEAN === $type => Filter::BOOLEAN,
            \in_array($type, self::DATE_TYPES, true) => Filter::DATE,
            \in_array($type, self::NUMBER_TYPES, true) => Filter::NUMBER,
            default => Filter::EXACT,
        };
    }

    /**
     * The constraints of a core filter value; none for the free texts (`exact`, `partial`) and
     * the custom filters.
     *
     * @param ClassMetadata<object>|null $metadata
     *
     * @return list<Constraint>|null
     */
    private function constraints(?ClassMetadata $metadata, string $property, string $type): ?array
    {
        $constraint = match ($type) {
            Filter::BOOLEAN => new ParameterValue([new Regex('/^(true|false|1|0)$/i')]),
            Filter::NUMBER => new ParameterValue([new Type('numeric')], operators: ['gt', 'gte', 'lt', 'lte', 'ne']),
            Filter::DATE => new ParameterValue([new Date()], operators: [DisplayDateFilter::AFTER, DisplayDateFilter::BEFORE]),
            Filter::ENUM => $this->enumConstraint($metadata, $property),
            Filter::RELATION => new ParameterValue(list: true),
            default => null,
        };

        return $constraint instanceof ParameterValue ? [$constraint] : null;
    }

    /**
     * A case value or name of the backed enum of the column, case-insensitive (EnumFilter).
     *
     * @param ClassMetadata<object>|null $metadata
     */
    private function enumConstraint(?ClassMetadata $metadata, string $property): ?ParameterValue
    {
        $enumClass = $metadata?->hasField($property) ? $metadata->getFieldMapping($property)->enumType : null;

        if (null === $enumClass || !is_subclass_of($enumClass, \BackedEnum::class)) {
            return null;
        }

        $accepted = [];

        foreach ($enumClass::cases() as $case) {
            $accepted[] = preg_quote((string) $case->value, '/');
            $accepted[] = preg_quote($case->name, '/');
        }

        return new ParameterValue(
            [new Regex('/^(' . implode('|', array_unique($accepted)) . ')$/i', message: 'The value you selected is not a valid choice.')],
            list: true,
        );
    }

    private function filter(string $type): FilterInterface|string
    {
        return match ($type) {
            Filter::EXACT => new ExactFilter(),
            Filter::PARTIAL => new PartialSearchFilter(),
            Filter::NUMBER => new ComparisonFilter(new ExactFilter()),
            Filter::DATE => self::DATE_FILTER,
            Filter::BOOLEAN => self::BOOLEAN_FILTER,
            Filter::ENUM => self::ENUM_FILTER,
            Filter::RELATION => self::RELATION_FILTER,
            // A filter service id, or the class of a filter without constructor arguments.
            default => !$this->isFilterService($type) && is_a($type, FilterInterface::class, true) ? new $type() : $type,
        };
    }

    private function isFilterService(string $type): bool
    {
        return !class_exists($type) || str_contains($type, '.');
    }

    /**
     * @param ClassMetadata<object>|null $metadata
     *
     * @return PanelField
     */
    private function panelField(?ClassMetadata $metadata, string $property, string $type, Filter $filter): array
    {
        $choices = $filter->choices;
        $entity = null;

        $enumClass = Filter::ENUM === $type && [] === $choices ? $metadata?->getFieldMapping($property)->enumType : null;

        if (null !== $enumClass && is_subclass_of($enumClass, \BackedEnum::class)) {
            foreach ($enumClass::cases() as $case) {
                $choices[$case->value] = $case->name;
            }
        }

        if (Filter::RELATION === $type && $metadata instanceof ClassMetadata && $metadata->hasAssociation($property)) {
            $entity = $metadata->getAssociationTargetClass($property);
        }

        $panelType = self::PANEL_TYPES[$type] ?? 'text';

        return [
            'type' => Filter::EXACT === $type && [] === $choices ? 'text' : $panelType,
            'label' => $filter->label,
            'choices' => $choices,
            'multiple' => $filter->multiple,
            'entity' => $entity,
            'resource' => $filter->resource,
            'template' => $filter->template,
        ];
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
            if (!$metadata->hasAssociation($association)) {
                return [];
            }

            $classes[] = $metadata->getName();
            $metadata = $this->metadata($metadata->getAssociationTargetClass($association));

            if (!$metadata instanceof ClassMetadata) {
                return [];
            }
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
        if (\is_string($value)) {
            return '' === trim($value) ? null : $value;
        }

        if (!\is_array($value)) {
            return $value;
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
