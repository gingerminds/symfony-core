<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Model;

/**
 * A property the lists filter on, API collections and admin lists alike: the query parameter
 * named after the property, applied by an API Platform filter, and the field of the admin
 * filters panel.
 *
 * `type` is an alias of a core filter, guessed from the Doctrine mapping when omitted:
 *
 * - `exact`: equality, `name=foo` or `name[]=foo&name[]=bar` (API Platform `ExactFilter`);
 * - `partial`: case-insensitive `LIKE`, `name=fo` (API Platform `PartialSearchFilter`);
 * - `number`: bounds, `price[gte]=10&price[lte]=100` (API Platform `ComparisonFilter`, also `gt`,
 *   `lt`, `ne`);
 * - `date`: whole days of the display timezone, `publishedAt[after]=2024-01-01&publishedAt[before]=2024-12-31`
 *   (`DisplayDateFilter`, both bounds inclusive);
 * - `boolean`: `active=true|false|1|0`, `false` also matches NULL (`NullableBooleanFilter`);
 * - `enum`: a backed enum, by value or case name, `status=draft` or `status[]=draft&status[]=archived` (`EnumFilter`);
 * - `relation`: the related ids of a to-one or to-many association, `category=5`, `tags[]=3&tags[]=4`,
 *   `category=null` for none (`RelationFilter`).
 *
 * Any other `type` is the service id (in the API Platform filter locator) or the class of an
 * API Platform Doctrine ORM filter: give its admin panel field a `template`.
 *
 * `label`, `choices` (value => label, translation keys), `multiple`, `resource` (remote
 * autocomplete of a relation) and `template` describe the admin panel field; `admin: false`
 * keeps the filter out of the panel (API only).
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final readonly class Filter
{
    public const string EXACT = 'exact';
    public const string PARTIAL = 'partial';
    public const string NUMBER = 'number';
    public const string DATE = 'date';
    public const string BOOLEAN = 'boolean';
    public const string ENUM = 'enum';
    public const string RELATION = 'relation';

    /**
     * @param array<int|string, string> $choices
     */
    public function __construct(
        public ?string $type = null,
        public ?string $label = null,
        public array $choices = [],
        public bool $multiple = false,
        public ?string $resource = null,
        public bool $admin = true,
        public ?string $template = null,
    ) {
    }
}
