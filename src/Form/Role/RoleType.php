<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Form\Role;

use Gingerminds\CoreBundle\Entity\Role\RoleInterface;
use Gingerminds\CoreBundle\Resource\ResourceRegistry;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<RoleInterface>
 */
class RoleType extends AbstractType
{
    public function __construct(
        protected readonly ResourceRegistry $resources,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'role.field.name',
            ])
            ->add('isExternal', CheckboxType::class, [
                'label' => 'role.field.is_external',
                'property_path' => 'external',
                'required' => false,
                'label_attr' => ['class' => 'checkbox-switch'],
                'size' => 'sm',
            ])
            ->add('isDefault', CheckboxType::class, [
                'label' => 'role.field.is_default',
                'property_path' => 'default',
                'required' => false,
                'label_attr' => ['class' => 'checkbox-switch'],
                'size' => 'sm',
            ])
            ->add('permissions', EntityType::class, [
                'label' => 'role.field.permissions',
                'class' => $this->resources->getEntityClass('permission'),
                'choice_label' => 'name',
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'by_reference' => false,
                'choice_translation_domain' => false,
                'query_builder' => static fn ($repository) => $repository->createQueryBuilder('p')->orderBy('p.name', 'ASC'),
                'size' => 'xl',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => $this->resources->getEntityClass('role'),
            'translation_domain' => 'GingermindsCore',
        ]);
    }
}
