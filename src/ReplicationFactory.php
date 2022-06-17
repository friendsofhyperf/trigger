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

use FriendsOfHyperf\Trigger\Process\ConsumeProcess;
use FriendsOfHyperf\Trigger\Subscriber\SnapshotSubscriber;
use FriendsOfHyperf\Trigger\Subscriber\TriggerSubscriber;
use FriendsOfHyperf\Trigger\Traits\Logger;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use MySQLReplication\Config\ConfigBuilder;
use MySQLReplication\MySQLReplicationFactory;

class ReplicationFactory
{
    use Logger;

    public function __construct(
        protected ConfigInterface $config,
        protected subscriberManager $subscriberManager,
        protected TriggerManager $triggerManager,
        protected StdoutLoggerInterface $logger
    ) {
    }

    public function make(ConsumeProcess $process): MySQLReplicationFactory
    {
        $replication = $process->getReplication();

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
        $configBuilder = tap(new ConfigBuilder(), function (ConfigBuilder $builder) use ($config, $databasesOnly, $tablesOnly) {
            $builder->withUser($config['user'] ?? 'root')
                ->withHost($config['host'] ?? '127.0.0.1')
                ->withPassword($config['password'] ?? 'root')
                ->withPort((int) $config['port'] ?? 3306)
                ->withSlaveId(random_int(100, 999))
                ->withHeartbeatPeriod((float) $config['heartbeat_period'] ?? 3)
                ->withDatabasesOnly($databasesOnly)
                ->withTablesOnly($tablesOnly);
        });

        if ($binLogCurrent = $process->getBinLogCurrentSnapshot()->get()) {
            $configBuilder->withBinLogFileName($binLogCurrent->getBinFileName());
            $configBuilder->withBinLogPosition((int) $binLogCurrent->getBinLogPosition());

            $this->info('Continue with position', $binLogCurrent->jsonSerialize());
        }

        $eventDispatcher = make(EventDispatcher::class, [
            'process' => $process,
        ]);

        return tap(make(MySQLReplicationFactory::class, [
            'config' => $configBuilder->build(),
            'eventDispatcher' => $eventDispatcher,
        ]), function ($factory) use ($replication, $process) {
            /** @var MySQLReplicationFactory $factory */
            $subscribers = $this->subscriberManager->get($replication);
            $subscribers[] = TriggerSubscriber::class;
            $subscribers[] = SnapshotSubscriber::class;

            foreach ($subscribers as $subscriber) {
                $factory->registerSubscriber(make($subscriber, ['process' => $process]));
            }
        });
    }
}
