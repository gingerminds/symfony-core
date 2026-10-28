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

        if (!\is_array($value) || [] === $value) {
            return null;
        }

        $isList = array_is_list($value);

        if ([] !== $constraint->operators ? $isList || [] !== array_diff(array_keys($value), $constraint->operators) : !$constraint->list || !$isList) {
            return null;
        }

        $items = [];

        foreach ($value as $key => $item) {
            if (!\is_scalar($item)) {
                return null;
            }

            $items['[' . $key . ']'] = $item;
        }

        return $items;
    }
}
