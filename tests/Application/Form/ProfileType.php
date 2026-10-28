<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Tests\Application\Form;

use Gingerminds\CoreBundle\Form\User\ProfileType as BaseProfileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Project profile form (`profile.form` of the `override` environment): the phone of the
 * overridden User.
 */
final class ProfileType extends BaseProfileType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        $builder->add('phone', TextType::class, ['required' => false]);
    }
}
