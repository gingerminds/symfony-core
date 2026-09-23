<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\ApiPlatform\State;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use Gingerminds\CoreBundle\Repository\RepositoryInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Contracts\Service\Attribute\Required;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @template T of object
 *
 * @implements ProcessorInterface<T|null, T|null>
 */
class ResourceProcessor implements ProcessorInterface
{
    /**
     * @param RepositoryInterface<T>             $repository
     * @param class-string<FormTypeInterface<T>> $formType
     */
    public function __construct(
        protected readonly RepositoryInterface $repository,
        protected readonly FormFactoryInterface $formFactory,
        protected readonly RequestStack $requestStack,
        protected readonly string $formType,
    ) {
    }

    protected ?TranslatorInterface $translator = null;

    #[Required]
    public function setTranslator(TranslatorInterface $translator): void
    {
        $this->translator = $translator;
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($operation instanceof DeleteOperationInterface) {
            if (\is_object($data)) {
                $this->remove($data, $operation);
            }

            return null;
        }

        $entity = \is_object($data) ? $data : $this->createEntity($operation, $context);
        $form = $this->formFactory->createNamed('', $this->formType, $entity, $this->getFormOptions($entity, $operation));
        $form->submit($this->getPayload(), !$operation instanceof Patch);

        if (!$form->isValid()) {
            throw new ValidationException($this->toViolations($form, $entity));
        }

        $this->repository->save($entity, $form);

        return $entity;
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return T
     */
    protected function createEntity(Operation $operation, array $context): object
    {
        $class = $this->repository->getEntityClass();

        return new $class();
    }

    /**
     * @param T $entity
     *
     * @return array<string, mixed>
     */
    protected function getFormOptions(object $entity, Operation $operation): array
    {
        return ['csrf_protection' => false];
    }

    /**
     * @param T $entity
     */
    protected function remove(object $entity, Operation $operation): void
    {
        $this->repository->remove($entity);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getPayload(): array
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request instanceof Request || '' === $request->getContent()) {
            return [];
        }

        try {
            return $request->toArray();
        } catch (\Throwable $exception) {
            throw new BadRequestHttpException($this->trans('api.error.invalid_json'), $exception);
        }
    }

    /**
     * @param array<string, mixed> $parameters
     */
    protected function trans(string $key, array $parameters = [], string $domain = 'GingermindsCore'): string
    {
        return $this->translator?->trans($key, $parameters, $domain) ?? $key;
    }

    /**
     * @param FormInterface<mixed> $form
     */
    protected function toViolations(FormInterface $form, object $root): ConstraintViolationList
    {
        $violations = new ConstraintViolationList();

        foreach ($form->getErrors(true) as $error) {
            if (!$error instanceof FormError) {
                continue;
            }

            $cause = $error->getCause();

            $violations->add(new ConstraintViolation(
                $error->getMessage(),
                $error->getMessageTemplate(),
                $error->getMessageParameters(),
                $root,
                $this->propertyPath($error->getOrigin()),
                $error->getOrigin()?->getViewData(),
                $error->getMessagePluralization(),
                $cause instanceof ConstraintViolationInterface ? $cause->getCode() : null,
                $cause instanceof ConstraintViolationInterface ? $cause->getConstraint() : null,
            ));
        }

        return $violations;
    }

    /**
     * @param FormInterface<mixed>|null $form
     */
    private function propertyPath(?FormInterface $form): string
    {
        $names = [];

        while ($form instanceof FormInterface && $form->getParent() instanceof FormInterface) {
            $names[] = $form->getName();
            $form = $form->getParent();
        }

        return implode('.', array_reverse($names));
    }
}
