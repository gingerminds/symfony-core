<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Validator;

use Gingerminds\CoreBundle\Model\TreeNodeInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class NoTreeCycleValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof NoTreeCycle) {
            throw new UnexpectedTypeException($constraint, NoTreeCycle::class);
        }

        if (null === $value) {
            return;
        }

        if (!$value instanceof TreeNodeInterface) {
            throw new UnexpectedValueException($value, TreeNodeInterface::class);
        }

        $visited = new \SplObjectStorage();

        // `$visited`: an existing cycle above the node does not loop forever.
        for ($ancestor = $value->getParent(); $ancestor instanceof TreeNodeInterface && !$visited->offsetExists($ancestor); $ancestor = $ancestor->getParent()) {
            if ($ancestor === $value) {
                $this->context->buildViolation($constraint->message)
                    ->atPath($constraint->errorPath)
                    ->setCode(NoTreeCycle::CYCLE_ERROR)
                    ->addViolation();

                return;
            }

            $visited->offsetSet($ancestor);
        }
    }
}
