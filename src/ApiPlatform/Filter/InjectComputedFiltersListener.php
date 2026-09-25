<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\ApiPlatform\Filter;

use ApiPlatform\Metadata\CollectionOperationInterface;
use Symfony\Component\HttpFoundation\Request;
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
        $resourceClass = $this->resolveResourceClass($event->getRequest());

        if (null === $resourceClass) {
            return;
        }

        $response = $event->getResponse();
        $body = $this->decodeBody($response);

        if (null === $body) {
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

    /**
     * The API resource class of a collection operation that has computed filters, null otherwise.
     */
    private function resolveResourceClass(Request $request): ?string
    {
        $resourceClass = $request->attributes->get('_api_resource_class');

        if (
            !\is_string($resourceClass)
            || !$request->attributes->get('_api_operation') instanceof CollectionOperationInterface
            || !$this->store->has($resourceClass)
        ) {
            return null;
        }

        return $resourceClass;
    }

    /**
     * The decoded JSON body of a successful response, null when there is nothing to enrich.
     *
     * @return array<mixed>|null
     */
    private function decodeBody(Response $response): ?array
    {
        $content = $response->getContent();

        if (false === $content || '' === $content || $response->getStatusCode() >= Response::HTTP_MULTIPLE_CHOICES) {
            return null;
        }

        try {
            $body = json_decode($content, true, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return \is_array($body) ? $body : null;
    }
}
