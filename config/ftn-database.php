<?php

declare(strict_types=1);

use App\Models\Area;
use App\Models\Message;

return [
    'load_migrations' => true,

    'models' => [
        'area' => Area::class,
        'message' => Message::class,
    ],
];
