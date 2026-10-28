<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * A TreeNodeInterface whose parent is neither itself nor one of its descendants.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class NoTreeCycle extends Constraint
{
    public const string CYCLE_ERROR = '5b1f6a3e-0c4d-4f3a-9b8e-2d7c1e6f4a90';

    protected const array ERROR_NAMES = [
        self::CYCLE_ERROR => 'CYCLE_ERROR',
    ];

    public string $message = 'tree.parent_cycle';

    /**
     * @param string        $errorPath the property of the violation, the parent
     * @param string[]|null $groups
     */
    public function __construct(
        public string $errorPath = 'parent',
        ?string $message = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct(null, $groups, $payload);

        $this->message = $message ?? $this->message;
    }

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
