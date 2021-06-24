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

use FriendsOfHyperf\Trigger\Subscriber\TriggerSubscriber;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use MySQLReplication\Config\ConfigBuilder;
use MySQLReplication\MySQLReplicationFactory;
use Psr\Container\ContainerInterface;

class ReplicationFactory
{
    /**
     * @var ConfigInterface
     */
    protected $config;

    /**
     * @var SubscriberManager
     */
    protected $subscriberManager;

    /**
     * @var TriggerManager
     */
    protected $triggerManager;

    /**
     * @var StdoutLoggerInterface
     */
    protected $logger;

    public function __construct(ContainerInterface $container)
    {
        $this->config = $container->get(ConfigInterface::class);
        $this->subscriberManager = $container->get(SubscriberManager::class);
        $this->triggerManager = $container->get(TriggerManager::class);
        $this->logger = $container->get(StdoutLoggerInterface::class);
    }

    public function make(string $replication = 'default'): MySQLReplicationFactory
    {
        // Get config of replication
        $config = $this->config->get('trigger.' . $replication);
        // Get databases of replication
        $databasesOnly = array_merge(
            $config['databases_only'] ?? [],
            $this->triggerManager->getDatabases($replication)
        );
        // Get tables of replication
        $tablesOnly = array_merge(
            $config['tables_only'] ?? [],
            $this->triggerManager->getTables($replication)
        );

        /** @var ConfigBuilder */
        $configBuilder = tap(new ConfigBuilder(), function ($builder) use ($config, $databasesOnly, $tablesOnly) {
            $builder->withUser($config['user'] ?? 'root')
                ->withHost($config['host'] ?? '127.0.0.1')
                ->withPassword($config['password'] ?? 'root')
                ->withPort((int) $config['port'] ?? 3306)
                ->withSlaveId(rand(100, 999))
                ->withHeartbeatPeriod((float) $config['heartbeat_period'] ?? 3)
                ->withDatabasesOnly($databasesOnly)
                ->withTablesOnly($tablesOnly);
        });

        if (isset($config['binlog_filename'])) {
            $configBuilder->withBinLogFileName($config['binlog_filename']);
        }

        if (isset($config['binlog_position'])) {
            $configBuilder->withBinLogPosition((int) $config['binlog_position']);
        }

        return tap(make(MySQLReplicationFactory::class, [
            'config' => $configBuilder->build(),
            'eventDispatcher' => make(EventDispatcher::class),
        ]), function ($factory) use ($replication) {
            /** @var MySQLReplicationFactory $factory */
            $subscribers = $this->subscriberManager->get($replication);
            $subscribers[] = TriggerSubscriber::class;

            foreach ($subscribers as $subscriber) {
                $factory->registerSubscriber(make($subscriber, ['replication' => $replication]));
            }
        });
    }
}
