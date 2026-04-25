<?php

declare(strict_types=1);

use Odinns\CodingStyle\OdinnsRectorConfig;
use Rector\Config\RectorConfig;

return static function (RectorConfig $rectorConfig): void {
    OdinnsRectorConfig::setup($rectorConfig);

    $rectorConfig->paths([
        __DIR__.'/app',
        __DIR__.'/bootstrap/app.php',
        __DIR__.'/bootstrap/providers.php',
        __DIR__.'/config',
        __DIR__.'/database',
        __DIR__.'/routes',
        __DIR__.'/tests',
    ]);
};
