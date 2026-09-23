<?php

declare(strict_types=1);

namespace Gingerminds\CoreBundle\Maker;

use Symfony\Bundle\MakerBundle\Exception\RuntimeCommandException;
use Symfony\Component\String\Inflector\EnglishInflector;

use function Symfony\Component\String\u;

final readonly class ResourceName
{
    /**
     * @param list<string> $namespaceParts
     */
    private function __construct(
        public array $namespaceParts,
        public string $name,
        public string $snake,
        public string $snakePlural,
        private string $rootNamespace,
    ) {
    }

    public static function fromInput(mixed $value, string $rootNamespace = 'App'): self
    {
        if (!\is_string($value) || '' === trim($value)) {
            throw new RuntimeCommandException('The resource name is required (e.g. "Product/Product" or "Media/MediaCategory").');
        }

        $parts = preg_split('#[/\\\\]+#', trim($value, " \t\n\r\0\x0B/\\"));

        if (false === $parts || [] === $parts) {
            throw new RuntimeCommandException(\sprintf('Invalid resource name "%s".', $value));
        }

        $parts = array_map(static function (string $part) use ($value): string {
            if (1 !== preg_match('/^[A-Za-z_]\w*$/', $part)) {
                throw new RuntimeCommandException(\sprintf('Invalid resource name "%s": "%s" is not a valid PHP identifier.', $value, $part));
            }

            return ucfirst($part);
        }, $parts);

        $name = array_pop($parts);
        $snake = u($name)->snake()->toString();

        return new self(
            $parts,
            $name,
            $snake,
            new EnglishInflector()->pluralize($snake)[0],
            trim($rootNamespace, '\\'),
        );
    }

    public function class(string $layerNamespace, string $suffix = ''): string
    {
        return implode('\\', array_filter([
            $this->rootNamespace,
            $layerNamespace,
            ...$this->namespaceParts,
            $this->name . $suffix,
        ], static fn (string $part): bool => '' !== $part));
    }

    public function entityClass(): string
    {
        return $this->class('Entity');
    }

    public function repositoryClass(): string
    {
        return $this->class('Repository', 'Repository');
    }

    public function formClass(): string
    {
        return $this->class('Form', 'Type');
    }

    public function controllerClass(): string
    {
        return $this->class('Controller', 'Controller');
    }

    public function voterClass(): string
    {
        return $this->class('Security\\Voter', 'Voter');
    }

    public function providerClass(): string
    {
        return $this->class('State', 'Provider');
    }

    public function processorClass(): string
    {
        return $this->class('State', 'Processor');
    }

    public function templateDirectory(): string
    {
        return 'admin/' . $this->snake;
    }

    public function label(bool $plural = false): string
    {
        return ucfirst(str_replace('_', ' ', $plural ? $this->snakePlural : $this->snake));
    }

    public function argument(): string
    {
        return implode('/', [...$this->namespaceParts, $this->name]);
    }

    public function relativeClassName(): string
    {
        return implode('\\', [...$this->namespaceParts, $this->name]);
    }
}
