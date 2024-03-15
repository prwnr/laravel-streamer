<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessParamTagRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessReturnTagRector;
use Rector\Php80\Rector\FunctionLike\MixedTypeRector;
use Rector\TypeDeclaration\Rector\ClassMethod\ReturnUnionTypeRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withPhpSets(php81: true)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
    )
    ->withRules([
        MixedTypeRector::class,
        ReturnUnionTypeRector::class,
        RemoveUselessReturnTagRector::class,
        RemoveUselessParamTagRector::class,
    ]);
