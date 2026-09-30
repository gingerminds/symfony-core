<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Form\ChoiceList;

use Gingerminds\CoreBundle\Entity\User\ContributorInterface;
use Gingerminds\CoreBundle\Repository\User\ContributorRepository;
use Gingerminds\CoreBundle\Repository\User\UserRepository;
use Symfony\Component\Form\ChoiceList\ArrayChoiceList;
use Symfony\Component\Form\ChoiceList\ChoiceListInterface;
use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;

/**
 * Choices of the user form contributor selector: "new contributor" plus the
 * contributors actually selected or submitted, loaded by id. The other contributors
 * are searched remotely: the whole table is never loaded.
 */
final class ContributorSelectorChoiceLoader implements ChoiceLoaderInterface
{
    /** @var array<string, string> label => contributor id (or UserRepository::NEW_CONTRIBUTOR) */
    private array $choices;

    private ?ChoiceListInterface $choiceList = null;

    public function __construct(
        private readonly ContributorRepository $contributors,
        string $newLabel,
    ) {
        $this->choices = [$newLabel => UserRepository::NEW_CONTRIBUTOR];
    }

    public function loadChoiceList(?callable $value = null): ChoiceListInterface
    {
        return $this->choiceList ??= new ArrayChoiceList($this->choices, $value);
    }

    public function loadChoicesForValues(array $values, ?callable $value = null): array
    {
        $this->load($values);

        return $this->loadChoiceList($value)->getChoicesForValues($values);
    }

    public function loadValuesForChoices(array $choices, ?callable $value = null): array
    {
        $this->load($choices);

        return $this->loadChoiceList($value)->getValuesForChoices($choices);
    }

    /**
     * @param array<mixed> $ids
     */
    private function load(array $ids): void
    {
        $missing = array_values(array_filter(
            array_map(static fn (mixed $id): string => \is_scalar($id) ? (string) $id : '', $ids),
            fn (string $id): bool => ctype_digit($id) && !\in_array($id, $this->choices, true),
        ));

        if ([] === $missing) {
            return;
        }

        foreach ($this->contributors->findBy(['id' => $missing]) as $contributor) {
            if ($contributor instanceof ContributorInterface) {
                // The id keeps the labels (choice keys) of homonyms unique.
                $this->choices[$contributor->getFullName() . ' (#' . $contributor->getId() . ')'] = (string) $contributor->getId();
            }
        }

        $this->choiceList = null;
    }
}
