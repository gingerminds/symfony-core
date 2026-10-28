<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\ApiPlatform\Filter;

use ApiPlatform\Doctrine\Orm\Filter\ComparisonFilter;
use ApiPlatform\Doctrine\Orm\Filter\ExactFilter;
use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Filter\PartialSearchFilter;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadata;
use Gingerminds\CoreBundle\ApiPlatform\Filter\Constraint\ParameterValue;
use Gingerminds\CoreBundle\Model\Filter;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Date;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Type;

/**
 * What a `#[Filter]` type gives its list parameter (FilterParameters): the type guessed from the
 * Doctrine mapping, the API Platform filter, the value constraints and the admin panel field.
 *
 * @phpstan-import-type PanelField from FilterParameters
 *
 * @internal
 */
final class FilterTypes
{
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

    /**
     * The type of a `#[Filter]` without `type`, from the Doctrine mapping of the property.
     *
     * @param ClassMetadata<object>|null $metadata
     */
    public static function guess(?ClassMetadata $metadata, string $property): string
    {
        // An association is no field.
        if (!$metadata instanceof ClassMetadata || !$metadata->hasField($property)) {
            return true === $metadata?->hasAssociation($property) ? Filter::RELATION : Filter::EXACT;
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
     * The API Platform filter of a filter type.
     */
    public static function filter(string $type): FilterInterface|string
    {
        return match ($type) {
            Filter::EXACT => new ExactFilter(),
            Filter::PARTIAL => new PartialSearchFilter(),
            Filter::NUMBER => new ComparisonFilter(new ExactFilter()),
            Filter::DATE => FilterParameters::DATE_FILTER,
            Filter::BOOLEAN => FilterParameters::BOOLEAN_FILTER,
            Filter::ENUM => FilterParameters::ENUM_FILTER,
            Filter::RELATION => FilterParameters::RELATION_FILTER,
            // A filter service id, or the class of a filter without constructor arguments.
            default => !self::isFilterService($type) && is_a($type, FilterInterface::class, true) ? new $type() : $type,
        };
    }

    private static function isFilterService(string $type): bool
    {
        return !class_exists($type) || str_contains($type, '.');
    }

    /**
     * The constraints of a core filter value; none for the free texts (`exact`, `partial`) and
     * the custom filters.
     *
     * @param ClassMetadata<object>|null $metadata
     *
     * @return list<Constraint>|null
     */
    public static function constraints(?ClassMetadata $metadata, string $property, string $type): ?array
    {
        $constraint = match ($type) {
            Filter::BOOLEAN => new ParameterValue([new Regex('/^(true|false|1|0)$/i')]),
            Filter::NUMBER => new ParameterValue([new Type('numeric')], operators: ['gt', 'gte', 'lt', 'lte', 'ne']),
            Filter::DATE => new ParameterValue([new Date()], operators: [DisplayDateFilter::AFTER, DisplayDateFilter::BEFORE]),
            Filter::ENUM => self::enumConstraint($metadata, $property),
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
    private static function enumConstraint(?ClassMetadata $metadata, string $property): ?ParameterValue
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

    /**
     * The admin filter panel field of a `#[Filter]` property.
     *
     * @param ClassMetadata<object>|null $metadata
     *
     * @return PanelField
     */
    public static function panelField(?ClassMetadata $metadata, string $property, string $type, Filter $filter): array
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
}
