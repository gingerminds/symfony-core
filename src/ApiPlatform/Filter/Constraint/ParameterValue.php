<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\ApiPlatform\Filter\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * The shape of a list parameter value, each scalar item checked against `constraints`:
 *
 * - a scalar (`status=draft`);
 * - with `list`, also a list of scalars (`status[]=draft&status[]=archived`);
 * - with `operators`, only an operator => scalar map (`price[gte]=10`, `publishedAt[after]=...`).
 */
final class ParameterValue extends Constraint
{
    public const string INVALID_SHAPE_ERROR = 'b2f0d6f4-6c8e-4a39-9a64-4f1c2c5f7a10';

    protected const array ERROR_NAMES = [
        self::INVALID_SHAPE_ERROR => 'INVALID_SHAPE_ERROR',
    ];

    public string $message = 'This value is not valid.';

    /**
     * @param list<Constraint> $constraints
     * @param list<string>     $operators
     * @param string[]|null    $groups
     */
    public function __construct(
        public array $constraints = [],
        public bool $list = false,
        public array $operators = [],
        ?string $message = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct(null, $groups, $payload);

        $this->message = $message ?? $this->message;
    }
}
