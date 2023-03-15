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

use FriendsOfHyperf\Trigger\Command\SubscribersCommand;
use FriendsOfHyperf\Trigger\Command\TriggersCommand;
use FriendsOfHyperf\Trigger\Listener\OnBootApplicationListener;
use FriendsOfHyperf\Trigger\Mutex\RedisServerMutex;
use FriendsOfHyperf\Trigger\Mutex\ServerMutexInterface;
use FriendsOfHyperf\Trigger\Snapshot\BinLogCurrentSnapshotInterface;
use FriendsOfHyperf\Trigger\Snapshot\RedisBinLogCurrentSnapshot;

class ConfigProvider
{
    public function __invoke(): array
    {
        defined('BASE_PATH') or define('BASE_PATH', '');

        return [
            'aspects' => [
                Aspect\BinaryDataReaderAspect::class, // Fix MySQLReplication bug
            ],
            'dependencies' => [
                ServerMutexInterface::class => RedisServerMutex::class,
                BinLogCurrentSnapshotInterface::class => RedisBinLogCurrentSnapshot::class,
            ],
            'commands' => [
                SubscribersCommand::class,
                TriggersCommand::class,
            ],
            'listeners' => [
                OnBootApplicationListener::class,
            ],
            'publish' => [
                [
                    'id' => 'config',
                    'description' => 'The config file of trigger.',
                    'source' => __DIR__ . '/../publish/trigger.php',
                    'destination' => BASE_PATH . '/config/autoload/trigger.php',
                ],
            ],
        ];
    }
}
