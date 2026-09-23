<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Form\User;

use Gingerminds\CoreBundle\Entity\User\ContributorInterface;
use Gingerminds\CoreBundle\Entity\User\UserInterface;
use Gingerminds\CoreBundle\Enum\Civility;
use Gingerminds\CoreBundle\Repository\User\ContributorRepository;
use Gingerminds\CoreBundle\Repository\User\UserRepository;
use Gingerminds\CoreBundle\Resource\ResourceRegistry;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractType<UserInterface>
 */
class UserType extends AbstractType
{
    public function __construct(
        protected readonly ResourceRegistry $resources,
        protected readonly ContributorRepository $contributors,
        protected readonly TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'user.field.email',
            ])
            ->add('password', RepeatedType::class, [
                'type' => PasswordType::class,
                'property_path' => 'plainPassword',
                'required' => $options['password_required'],
                'first_options' => ['label' => 'user.field.password', 'size' => 'md', 'always_empty' => true],
                'second_options' => ['label' => 'user.field.password_confirmation', 'size' => 'md', 'always_empty' => true],
                'invalid_message' => 'user.password.mismatch',
                'constraints' => $options['password_required'] ? [new Assert\NotBlank()] : [],
            ])
            ->add('roles', EntityType::class, [
                'label' => 'user.field.roles',
                'class' => $this->resources->getEntityClass('role'),
                'property_path' => 'roleEntities',
                'choice_label' => 'name',
                'multiple' => true,
                'required' => false,
                'autocomplete' => true,
                'by_reference' => false,
            ])
            ->add('contributorId', ChoiceType::class, [
                'label' => 'user.field.contributor',
                'mapped' => false,
                'required' => false,
                'placeholder' => 'user.contributor.none',
                'choices' => $this->contributorChoices(),
                'choice_translation_domain' => false,
                'autocomplete' => true,
            ]);

        $this->addContributorFields($builder);

        $builder->addEventListener(FormEvents::POST_SET_DATA, static function (FormEvent $event): void {
            $user = $event->getData();
            $contributor = $user instanceof UserInterface ? $user->getContributor() : null;

            if (!$contributor instanceof ContributorInterface) {
                return;
            }

            $form = $event->getForm();

            if ($form->has('contributorId')) {
                $form->get('contributorId')->setData((string) $contributor->getId());
            }

            $form->get('contributorFirstname')->setData($contributor->getFirstname());
            $form->get('contributorLastname')->setData($contributor->getLastname());
            $form->get('contributorTrigram')->setData($contributor->getTrigram());
            $form->get('contributorCivility')->setData($contributor->getCivility());
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => $this->resources->getEntityClass('user'),
            'translation_domain' => 'GingermindsCore',
            'password_required' => false,
        ]);
        $resolver->setAllowedTypes('password_required', 'bool');
    }

    /**
     * @param FormBuilderInterface<UserInterface|null> $builder
     */
    protected function addContributorFields(FormBuilderInterface $builder): void
    {
        $builder
            ->add('contributorCivility', EnumType::class, [
                'label' => 'contributor.field.civility',
                'class' => Civility::class,
                'mapped' => false,
                'required' => false,
                'placeholder' => '',
                'size' => 'tiny',
            ])
            ->add('contributorLastname', TextType::class, [
                'label' => 'contributor.field.lastname',
                'mapped' => false,
                'required' => false,
                'constraints' => [new Assert\Length(max: 255)],
                'size' => 'sm',
            ])
            ->add('contributorFirstname', TextType::class, [
                'label' => 'contributor.field.firstname',
                'mapped' => false,
                'required' => false,
                'constraints' => [new Assert\Length(max: 255)],
                'size' => 'sm',
            ])
            ->add('contributorTrigram', TextType::class, [
                'label' => 'contributor.field.trigram',
                'mapped' => false,
                'required' => false,
                'constraints' => [new Assert\Length(max: 3)],
                'size' => 'tiny',
            ]);
    }

    /**
     * @return array<string, string>
     */
    protected function contributorChoices(): array
    {
        $choices = [$this->translator->trans('user.contributor.new', [], 'GingermindsCore') => UserRepository::NEW_CONTRIBUTOR];

        foreach ($this->contributors->findAllOrdered() as $contributor) {
            $choices[$contributor->getFullName() . ' (#' . $contributor->getId() . ')'] = (string) $contributor->getId();
        }

        return $choices;
    }
}
