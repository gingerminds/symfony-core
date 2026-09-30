<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Security\Api;

use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use Psr\Container\ContainerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class ApiRateLimitListener
{
    public const string DEFAULT_LIMITER = 'gingerminds_core_api';
    public const string ATTRIBUTE = '_rate_limiter';

    private const string RATE_LIMIT_ATTRIBUTE = '_gingerminds_rate_limit';

    public function __construct(
        private ContainerInterface $limiters,
        private ResourceMetadataCollectionFactoryInterface $resourceMetadataFactory,
        private Security $security,
        private TranslatorInterface $translator,
        private string $apiPrefix,
        private bool $enabled,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $name = $this->limiterName($request);

        if (null === $name) {
            return;
        }

        $factory = $this->limiters->get($name);

        if (!$factory instanceof RateLimiterFactoryInterface) {
            throw new \LogicException(\sprintf('The "%s" rate limiter does not exist, declare it under "framework.rate_limiter".', $name));
        }

        $rateLimit = $factory->create($this->key($request))->consume();
        $request->attributes->set(self::RATE_LIMIT_ATTRIBUTE, $rateLimit);

        if (!$rateLimit->isAccepted()) {
            $event->setResponse(new JsonResponse(
                ['message' => $this->translator->trans('security.api.too_many_requests', [], 'security')],
                Response::HTTP_TOO_MANY_REQUESTS,
            ));
        }
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $rateLimit = $event->getRequest()->attributes->get(self::RATE_LIMIT_ATTRIBUTE);

        if (!$event->isMainRequest() || !$rateLimit instanceof RateLimit) {
            return;
        }

        $headers = $event->getResponse()->headers;
        $headers->set('X-RateLimit-Limit', (string) $rateLimit->getLimit());
        $headers->set('X-RateLimit-Remaining', (string) $rateLimit->getRemainingTokens());
        $headers->set('X-RateLimit-Reset', (string) $rateLimit->getRetryAfter()->getTimestamp());

        if (!$rateLimit->isAccepted()) {
            $headers->set('Retry-After', (string) max(0, $rateLimit->getRetryAfter()->getTimestamp() - time()));
        }
    }

    private function limiterName(Request $request): ?string
    {
        $name = $request->attributes->get(self::ATTRIBUTE) ?? $this->operationLimiter($request);

        if (null === $name) {
            return $this->enabled && $this->isApiRequest($request) ? self::DEFAULT_LIMITER : null;
        }

        return \is_string($name) && '' !== $name ? $name : null;
    }

    private function operationLimiter(Request $request): mixed
    {
        $resourceClass = $request->attributes->get('_api_resource_class');
        $operationName = $request->attributes->get('_api_operation_name');

        if (!\is_string($resourceClass) || !\is_string($operationName)) {
            return null;
        }

        $operation = $this->resourceMetadataFactory->create($resourceClass)->getOperation($operationName);

        return $operation->getExtraProperties()[self::ATTRIBUTE] ?? $operation->getExtraProperties()['rate_limiter'] ?? null;
    }

    private function isApiRequest(Request $request): bool
    {
        $prefix = '/' . $this->apiPrefix;
        $path = $request->getPathInfo();

        return $path === $prefix || str_starts_with($path, $prefix . '/');
    }

    private function key(Request $request): string
    {
        $user = $this->security->getUser();

        return null !== $user ? 'user:' . $user->getUserIdentifier() : 'ip:' . $request->getClientIp();
    }
}
