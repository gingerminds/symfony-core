<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Form\Extension;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * `input_prefix` / `input_suffix`: texts shown before / after a single line input, in a Bootstrap
 * input group (Laravel `form.inputs.basic` prefix / suffix). Not translated.
 */
final class InputGroupTypeExtension extends AbstractTypeExtension
{
    public static function getExtendedTypes(): iterable
    {
        return [FormType::class];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['input_prefix' => null, 'input_suffix' => null]);
        $resolver->setAllowedTypes('input_prefix', ['null', 'string']);
        $resolver->setAllowedTypes('input_suffix', ['null', 'string']);
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['input_prefix'] = $options['input_prefix'];
        $view->vars['input_suffix'] = $options['input_suffix'];
    }
}
