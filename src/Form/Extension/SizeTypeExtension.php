<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Form\Extension;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class SizeTypeExtension extends AbstractTypeExtension
{
    public const array COLUMNS = ['tiny' => 2, 'sm' => 4, 'md' => 6, 'lg' => 8, 'xl' => 12];

    public static function getExtendedTypes(): iterable
    {
        return [FormType::class];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('size', null);
        $resolver->setAllowedValues('size', [null, ...array_keys(self::COLUMNS)]);
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $size = $options['size'] ?? ($form->isRoot() || $form->getConfig()->getCompound() ? null : 'md');
        $view->vars['size'] = $size;
        $view->vars['size_columns'] = null !== $size ? self::COLUMNS[$size] : null;
    }
}
