<?php

declare(strict_types=1);
/**
 * This file is part of hyperf-trigger.
 *
 * @link     https://github.com/friendsofhyperf/trigger
 * @document https://github.com/friendsofhyperf/trigger/blob/main/README.md
 * @contact  huangdijia@gmail.com
 */
return [
    'default' => [
        'host' => env('TRIGGER_HOST', ''),
        'port' => env('TRIGGER_PORT', 3306),
        'user' => env('TRIGGER_USER', ''),
        'password' => env('TRIGGER_PASSWORD', ''),
        'databases_only' => env('TRIGGER_DATABASES_ONLY', '') ? explode(',', env('TRIGGER_DATABASES_ONLY')) : [],
        'tables_only' => env('TRIGGER_TABLES_ONLY', '') ? explode(',', env('TRIGGER_TABLES_ONLY')) : [],
        'heartbeat_period' => (int) env('TRIGGER_HEARTBEAT', 3),

        'processes' => 1,
        'concurrent' => [
            'limit' => 64,
        ],
    ],
];
