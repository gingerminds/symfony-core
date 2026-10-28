<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Controller;

use Doctrine\Persistence\ManagerRegistry;
use Gingerminds\CoreBundle\ApiPlatform\Filter\FilterParameters;
use Gingerminds\CoreBundle\Resource\ResourceRegistry;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The services of a resource controller (an AbstractController): subscribed, and read from
 * its service locator.
 */
trait ResourceControllerTrait
{
    public static function getSubscribedServices(): array
    {
        return [
            ...parent::getSubscribedServices(),
            ResourceRegistry::class => ResourceRegistry::class,
            FilterParameters::class => FilterParameters::class,
            'doctrine' => ManagerRegistry::class,
            'translator' => TranslatorInterface::class,
            'validator' => ValidatorInterface::class,
        ];
    }

    protected function getResourceRegistry(): ResourceRegistry
    {
        $resources = $this->container->get(ResourceRegistry::class);
        \assert($resources instanceof ResourceRegistry);

        return $resources;
    }

    protected function getDoctrine(): ManagerRegistry
    {
        $doctrine = $this->container->get('doctrine');
        \assert($doctrine instanceof ManagerRegistry);

        return $doctrine;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    protected function trans(string $key, array $parameters = [], string $domain = 'GingermindsCore'): string
    {
        $translator = $this->container->get('translator');
        \assert($translator instanceof TranslatorInterface);

        return $translator->trans($key, $parameters, $domain);
    }

    protected function getFilterParameters(): FilterParameters
    {
        $filterParameters = $this->container->get(FilterParameters::class);
        \assert($filterParameters instanceof FilterParameters);

        return $filterParameters;
    }

    protected function getValidator(): ValidatorInterface
    {
        $validator = $this->container->get('validator');
        \assert($validator instanceof ValidatorInterface);

        return $validator;
    }

    private function getRequestStack(): RequestStack
    {
        $requestStack = $this->container->get('request_stack');
        \assert($requestStack instanceof RequestStack);

        return $requestStack;
    }
}
