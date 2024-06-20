<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\TypeDeclaration\Rector\ClassMethod\AddVoidReturnTypeWhereNoReturnRector;
use Rector\PHPUnit\Set\PHPUnitSetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
//        __DIR__ . '/tests',
    ])
    ->withSets([
        PHPUnitSetList::PHPUNIT_100,
        PHPUnitSetList::ANNOTATIONS_TO_ATTRIBUTES,
    ])
// uncomment to reach your current PHP version
    // ->withPhpSets()
    ->withRules([
        AddVoidReturnTypeWhereNoReturnRector::class,
    ]);
