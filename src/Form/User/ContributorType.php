<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Form\User;

use Gingerminds\CoreBundle\Entity\User\ContributorInterface;
use Gingerminds\CoreBundle\Enum\Civility;
use Gingerminds\CoreBundle\Resource\ResourceRegistry;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<ContributorInterface>
 */
class ContributorType extends AbstractType
{
    public function __construct(
        protected readonly ResourceRegistry $resources,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('civility', EnumType::class, [
                'label' => 'contributor.field.civility',
                'class' => Civility::class,
                'required' => false,
                'placeholder' => '',
                'size' => 'tiny',
            ])
            ->add('lastname', TextType::class, [
                'label' => 'contributor.field.lastname',
                'size' => 'sm',
            ])
            ->add('firstname', TextType::class, [
                'label' => 'contributor.field.firstname',
                'size' => 'sm',
            ])
            ->add('trigram', TextType::class, [
                'label' => 'contributor.field.trigram',
                'required' => false,
                'size' => 'tiny',
            ])
            ->add('user', EntityType::class, [
                'label' => 'contributor.field.user',
                'class' => $this->resources->getEntityClass('user'),
                'choice_label' => 'email',
                'required' => false,
                'placeholder' => '',
                'autocomplete' => true,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => $this->resources->getEntityClass('contributor'),
            'translation_domain' => 'GingermindsCore',
        ]);
    }
}
