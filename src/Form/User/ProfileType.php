<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Form\User;

use Symfony\Component\Form\FormBuilderInterface;

class ProfileType extends UserType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        $builder
            ->remove('roles')
            ->remove('contributorId');
    }
}
