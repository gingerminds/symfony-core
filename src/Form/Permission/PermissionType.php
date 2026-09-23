<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Form\Permission;

use Gingerminds\CoreBundle\Entity\Permission\PermissionInterface;
use Gingerminds\CoreBundle\Resource\ResourceRegistry;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<PermissionInterface>
 */
class PermissionType extends AbstractType
{
    public function __construct(
        protected readonly ResourceRegistry $resources,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('name', TextType::class, [
            'label' => 'permission.field.name',
            'help' => 'permission.help.name',
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => $this->resources->getEntityClass('permission'),
            'translation_domain' => 'GingermindsCore',
        ]);
    }
}
