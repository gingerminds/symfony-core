<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\ApiPlatform\Filter\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class ParameterValueValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ParameterValue) {
            throw new UnexpectedTypeException($constraint, ParameterValue::class);
        }

        if (null === $value) {
            return;
        }

        $items = $this->items($value, $constraint);

        if (null === $items) {
            $this->context->buildViolation($constraint->message)
                ->setCode(ParameterValue::INVALID_SHAPE_ERROR)
                ->addViolation();

            return;
        }

        $validator = $this->context->getValidator()->inContext($this->context);

        foreach ($items as $path => $item) {
            $validator->atPath($path)->validate($item, $constraint->constraints);
        }
    }

    /**
     * The scalar items by property path, null when the value has not the expected shape.
     *
     * @return array<string, scalar>|null
     */
    private function items(mixed $value, ParameterValue $constraint): ?array
    {
        if (\is_scalar($value)) {
            return [] === $constraint->operators ? ['' => $value] : null;
        }

        if (!\is_array($value) || !$this->hasExpectedKeys($value, $constraint)) {
            return null;
        }

        $items = [];

        foreach ($value as $key => $item) {
            if (\is_scalar($item)) {
                $items['[' . $key . ']'] = $item;
            }
        }

        // A non scalar item makes the whole value invalid.
        return \count($items) === \count($value) ? $items : null;
    }

    /**
     * Operators (`[gte]`, `[after]`...) as the only keys of a parameter with operators, a list for
     * a list parameter.
     *
     * @param array<mixed> $value
     */
    private function hasExpectedKeys(array $value, ParameterValue $constraint): bool
    {
        if ([] === $value) {
            return false;
        }

        $isList = array_is_list($value);

        return [] !== $constraint->operators
            ? !$isList && [] === array_diff(array_keys($value), $constraint->operators)
            : $constraint->list && $isList;
    }
}
