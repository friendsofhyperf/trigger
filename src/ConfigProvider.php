<?php

declare(strict_types=1);
/**
 * This file is part of hyperf-trigger.
 *
 * @link     https://github.com/friendsofhyperf/trigger
 * @document https://github.com/friendsofhyperf/trigger/blob/main/README.md
 * @contact  huangdijia@gmail.com
 */
namespace FriendsOfHyperf\Trigger;

class ConfigProvider
{
    public function __invoke(): array
    {
        defined('BASE_PATH') or define('BASE_PATH', '');

        return [
            'dependencies' => [
                PositionFactory::class => PositionFactory::class,
                ReplicationFactory::class => ReplicationFactory::class,
                SubscriberManagerFactory::class => SubscriberManagerFactory::class,
                TriggerManagerFactory::class => TriggerManagerFactory::class,
            ],
            'annotations' => [
                'scan' => [
                    'paths' => [
                        __DIR__,
                    ],
                ],
            ],
            'commands' => [],
            'listeners' => [
                Listener\RegisterTriggerListener::class,
                Listener\RegisterSubsciberListener::class,
            ],
            'publish' => [
                [
                    'id' => 'config',
                    'description' => 'config file.',
                    'source' => __DIR__ . '/../publish/trigger.php',
                    'destination' => BASE_PATH . '/config/autoload/trigger.php',
                ],
            ],
        ];
    }
}
