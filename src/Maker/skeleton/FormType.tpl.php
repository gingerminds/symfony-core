<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $namespace ?>;

<?= $use_statements ?>

/**
 * Used by the admin CRUD and the API processor.
 *
 * @extends AbstractType<<?= $entity_class ?>>
 */
final class <?= $class_name ?> extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => '<?= $resource->snake ?>.field.name',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => <?= $entity_class ?>::class,
            'translation_domain' => 'admin',
        ]);
    }
}
