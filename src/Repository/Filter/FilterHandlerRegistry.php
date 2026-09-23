<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Repository\Filter;

use Gingerminds\CoreBundle\Repository\Filter\Handler\BooleanFilterHandler;
use Gingerminds\CoreBundle\Repository\Filter\Handler\DateFilterHandler;
use Gingerminds\CoreBundle\Repository\Filter\Handler\NumberFilterHandler;
use Gingerminds\CoreBundle\Repository\Filter\Handler\SelectEntityFilterHandler;
use Gingerminds\CoreBundle\Repository\Filter\Handler\SelectEnumFilterHandler;
use Gingerminds\CoreBundle\Repository\Filter\Handler\SelectFilterHandler;
use Psr\Container\ContainerInterface;

final class FilterHandlerRegistry
{
    /** @var array<string, FilterHandlerInterface> */
    private array $handlers = [];

    public function __construct(
        private readonly ?ContainerInterface $locator = null,
    ) {
    }

    public static function withDefaults(): self
    {
        $registry = new self();
        $registry->register('boolean', new BooleanFilterHandler());
        $registry->register('date', new DateFilterHandler());
        $registry->register('number', new NumberFilterHandler());
        $registry->register('select', new SelectFilterHandler());
        $registry->register('select-entity', $selectEntity = new SelectEntityFilterHandler());
        $registry->register('select-model', $selectEntity);
        $registry->register('select-enum', $selectEnum = new SelectEnumFilterHandler());
        $registry->register('select-state', $selectEnum);

        return $registry;
    }

    public function register(string $type, FilterHandlerInterface $handler): void
    {
        $this->handlers[$type] = $handler;
    }

    public function has(string $type): bool
    {
        return isset($this->handlers[$type]) || (bool) $this->locator?->has($type);
    }

    public function get(string $type): ?FilterHandlerInterface
    {
        if (isset($this->handlers[$type])) {
            return $this->handlers[$type];
        }

        if ($this->locator instanceof ContainerInterface && $this->locator->has($type)) {
            $handler = $this->locator->get($type);

            return $handler instanceof FilterHandlerInterface ? $handler : null;
        }

        return null;
    }
}
