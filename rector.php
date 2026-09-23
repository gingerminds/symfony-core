<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Symfony\Set\SymfonySetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/config',
        __DIR__ . '/tests',
    ])
    ->withSkip([
        // Keep the explicit `= null` of the #[Required] setter-injected registry (lazy fallback).
        Rector\DeadCode\Rector\Property\RemoveDefaultValueFromAssignedPropertyRector::class => [
            __DIR__ . '/src/Repository/AbstractRepository.php',
        ],
        __DIR__ . '/tests/Application/config/reference.php',
        __DIR__ . '/src/Maker/skeleton',
        __DIR__ . '/tests/Application/var',
        __DIR__ . '/tests/Application/assets',
    ])
    ->withPhpSets(php84: true)
    ->withPreparedSets(deadCode: true, codeQuality: true, typeDeclarations: true)
    ->withAttributesSets(symfony: true, doctrine: true)
    ->withSets([SymfonySetList::SYMFONY_CODE_QUALITY])
    ->withImportNames(importShortClasses: false, removeUnusedImports: true);
