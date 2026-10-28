<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Form\User;

use Gingerminds\CoreBundle\Entity\User\BaseUser;
use Gingerminds\CoreBundle\Entity\User\ContributorInterface;
use Gingerminds\CoreBundle\Entity\User\UserInterface;
use Gingerminds\CoreBundle\Enum\Civility;
use Gingerminds\CoreBundle\Form\Type\ContributorSelectorType;
use Gingerminds\CoreBundle\Repository\User\ContributorRepositoryInterface;
use Gingerminds\CoreBundle\Resource\ResourceRegistry;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The user and its contributor: the unmapped `contributor*` fields create, select or edit the
 * contributor of the user once the form is valid (`contributorId`: an existing contributor id,
 * or NEW_CONTRIBUTOR).
 *
 * @extends AbstractType<UserInterface>
 */
class UserType extends AbstractType
{
    public const string NEW_CONTRIBUTOR = '__new__';

    public function __construct(
        protected readonly ResourceRegistry $resources,
        protected readonly ContributorRepositoryInterface $contributors,
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
            // "New contributor" or an existing one, searched remotely (never the whole table).
            ->add('contributorId', ContributorSelectorType::class, [
                'label' => 'user.field.contributor',
                'mapped' => false,
                'required' => false,
                'placeholder' => 'user.contributor.none',
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

        // After the validation listener (priority 0): only a valid submission changes the contributor.
        $builder->addEventListener(FormEvents::POST_SUBMIT, $this->submitContributor(...), -10);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => $this->resources->getEntityClass('user'),
            'translation_domain' => 'GingermindsCore',
            'password_required' => false,
            // The password is required on creation (BaseUser::GROUP_CREATE), as on the API.
            'validation_groups' => static fn (Options $options): array => $options['password_required'] ? ['Default', BaseUser::GROUP_CREATE] : ['Default'],
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

    protected function submitContributor(FormEvent $event): void
    {
        $form = $event->getForm();
        $user = $event->getData();

        if (!$user instanceof UserInterface || !$form->isValid() || (!$form->has('contributorId') && !$form->has('contributorLastname'))) {
            return;
        }

        $selector = $form->has('contributorId')
            ? $form->get('contributorId')->getData()
            : ($user->getContributor()?->getId() ?? self::NEW_CONTRIBUTOR);

        if (null === $selector || '' === $selector) {
            return;
        }

        $contributorClass = $this->resources->getEntityClass('contributor');
        // A new contributor is persisted with the user (cascade persist of BaseUser::$contributor).
        $contributor = self::NEW_CONTRIBUTOR === $selector ? new $contributorClass() : $this->contributors->find($selector);

        if (!$contributor instanceof ContributorInterface) {
            return;
        }

        $this->fillContributor($contributor, $form);
        // Releases the contributor left (BaseUser::setContributor()).
        $user->setContributor($contributor);
    }

    /**
     * @param FormInterface<mixed> $form
     */
    protected function fillContributor(ContributorInterface $contributor, FormInterface $form): void
    {
        $value = static fn (string $field): mixed => $form->has($field) ? $form->get($field)->getData() : null;

        if (null !== $firstname = $value('contributorFirstname')) {
            $contributor->setFirstname((string) $firstname);
        }

        if (null !== $lastname = $value('contributorLastname')) {
            $contributor->setLastname((string) $lastname);
        }

        $contributor->setFirstname($contributor->getFirstname() ?? '');
        $contributor->setLastname($contributor->getLastname() ?? '');

        if (null !== $trigram = $value('contributorTrigram')) {
            $contributor->setTrigram((string) $trigram);
        }

        $civility = $value('contributorCivility');

        if ($civility instanceof Civility) {
            $contributor->setCivility($civility);
        }
    }

    /**
     * @deprecated loads every contributor: the contributorId field uses
     *             ContributorSelectorType (remote search) instead
     *
     * @return array<string, string>
     */
    protected function contributorChoices(): array
    {
        $choices = [$this->translator->trans('user.contributor.new', [], 'GingermindsCore') => self::NEW_CONTRIBUTOR];

        foreach ($this->contributors->findAllOrdered() as $contributor) {
            $choices[$contributor->getFullName() . ' (#' . $contributor->getId() . ')'] = (string) $contributor->getId();
        }

        return $choices;
    }
}
