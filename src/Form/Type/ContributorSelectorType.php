<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Form\Type;

use Gingerminds\CoreBundle\Form\ChoiceList\ContributorSelectorChoiceLoader;
use Gingerminds\CoreBundle\Repository\User\ContributorRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Contributor of the user form: "new contributor" or an existing contributor id
 * (string, see UserRepository::handleContributor()), searched remotely through
 * `gingerminds_core_autocomplete` instead of listing every contributor.
 *
 * @extends AbstractType<string|null>
 */
final class ContributorSelectorType extends AbstractType
{
    public function __construct(
        private readonly ContributorRepository $contributors,
        private readonly TranslatorInterface $translator,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'choice_loader' => fn (Options $options): ContributorSelectorChoiceLoader => new ContributorSelectorChoiceLoader(
                $this->contributors,
                $this->translator->trans('user.contributor.new', [], 'GingermindsCore'),
            ),
            'choice_translation_domain' => false,
            'autocomplete' => true,
            'autocomplete_url' => fn (Options $options): string => $this->urlGenerator->generate('gingerminds_core_autocomplete', ['resource' => 'contributor']),
        ]);
    }

    public function getParent(): string
    {
        return ChoiceType::class;
    }
}
