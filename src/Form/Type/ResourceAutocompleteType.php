<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Form\Type;

use Gingerminds\CoreBundle\Resource\ResourceRegistry;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;
use Symfony\Component\Form\ChoiceList\Loader\LazyChoiceLoader;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @extends AbstractType<object|iterable<object>|null>
 */
final class ResourceAutocompleteType extends AbstractType
{
    public function __construct(
        private readonly ResourceRegistry $resources,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('resource');
        $resolver->setAllowedTypes('resource', 'string');

        $resolver->setDefaults([
            'class' => fn (Options $options): string => $this->resources->getEntityClass($options['resource']),
            'choice_loader' => static fn (Options $options, ?ChoiceLoaderInterface $loader): ?ChoiceLoaderInterface => null === $loader ? null : new LazyChoiceLoader($loader),
            'autocomplete' => true,
            'autocomplete_url' => fn (Options $options): string => $this->urlGenerator->generate('gingerminds_core_autocomplete', ['resource' => $options['resource']]),
        ]);
    }

    public function getParent(): string
    {
        return EntityType::class;
    }
}
