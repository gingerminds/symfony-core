<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\ApiPlatform\Filter;

use ApiPlatform\Metadata\CollectionOperationInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

final readonly class InjectComputedFiltersListener
{
    public function __construct(
        private ComputedFilterStore $store,
    ) {
    }

    public function __invoke(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $resourceClass = $request->attributes->get('_api_resource_class');

        if (
            !\is_string($resourceClass)
            || !$request->attributes->get('_api_operation') instanceof CollectionOperationInterface
            || !$this->store->has($resourceClass)
        ) {
            return;
        }

        $response = $event->getResponse();
        $content = $response->getContent();

        if (false === $content || '' === $content || $response->getStatusCode() >= Response::HTTP_MULTIPLE_CHOICES) {
            return;
        }

        try {
            $body = json_decode($content, true, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return;
        }

        if (!\is_array($body)) {
            return;
        }

        $filters = array_filter(
            $this->store->get($resourceClass),
            static fn (array $filter): bool => [] !== ($filter['options'] ?? []),
        );

        if (array_is_list($body)) {
            $body = ['member' => $body];
        }

        $body['filters'] = $filters;
        $response->setContent(json_encode($body, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_PRESERVE_ZERO_FRACTION));
    }
}
