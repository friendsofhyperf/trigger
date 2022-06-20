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

use FriendsOfHyperf\Trigger\Monitor\HealthMonitor;
use FriendsOfHyperf\Trigger\Mutex\ServerMutexInterface;
use FriendsOfHyperf\Trigger\Snapshot\BinLogCurrentSnapshotInterface;
use FriendsOfHyperf\Trigger\Subscriber\SnapshotSubscriber;
use FriendsOfHyperf\Trigger\Subscriber\TriggerSubscriber;
use FriendsOfHyperf\Trigger\Traits\Logger;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Coordinator\Constants;
use Hyperf\Coordinator\CoordinatorManager;
use Hyperf\Utils\Arr;
use Hyperf\Utils\Coroutine;
use MySQLReplication\BinLog\BinLogCurrent;
use MySQLReplication\Config\ConfigBuilder;
use MySQLReplication\MySQLReplicationFactory;

class Replication
{
    use Logger;

    private string $replication;

    private array $options;

    private ?HealthMonitor $healthMonitor;

    private ?ServerMutexInterface $serverMutex;

    private BinLogCurrentSnapshotInterface $binLogCurrentSnapshot;

    private bool $stopped = false;

    public function __construct(
        protected subscriberManager $subscriberManager,
        protected TriggerManager $triggerManager,
        protected StdoutLoggerInterface $logger,
        string $replication,
        array $options = []
    ) {
        $this->replication = $replication;
        $this->options = $options;

        $this->makeBinLogCurrentSnapshot();

        if ($this->getOption('server_mutex.enable', true)) {
            $this->serverMutex = $this->makeServerMutex();
        }

        if ($this->getOption('health_monitor.enable', true)) {
            $this->healthMonitor = $this->makeHealthMonitor();
        }
    }

    public function getOption(?string $key = null, $default = null)
    {
        if (is_null($key)) {
            return $this->options;
        }

        return Arr::get($this->options, $key, $default);
    }

    public function getHealthMonitor(): ?HealthMonitor
    {
        return $this->healthMonitor;
    }

    public function getBinLogCurrentSnapshot(): BinLogCurrentSnapshotInterface
    {
        return $this->binLogCurrentSnapshot;
    }

    public function getReplication(): string
    {
        return $this->replication;
    }

    public function stop(): void
    {
        $this->stopped = true;

        if ($this->serverMutex) {
            $this->serverMutex->release();
        }
    }

    public function isStopped(): bool
    {
        return $this->stopped;
    }

    public function callOnReplicationStopped($binLogCurrent)
    {
        $this->onReplicationStopped($binLogCurrent);
    }

    public function start(): void
    {
        $callback = function () {
            // Health monitor
            if ($this->healthMonitor) {
                $this->healthMonitor->process();
            }

            $replication = $this->makeReplication();

            // Replication start
            CoordinatorManager::until($this->getIdentifier())->resume();

            $this->debug('Process started.');

            // Worker exit
            Coroutine::create(function () {
                CoordinatorManager::until(Constants::WORKER_EXIT)->yield();

                $this->stop();

                $this->warning('Process stopped.');
            });

            while (1) {
                if ($this->isStopped()) {
                    break;
                }

                $replication->consume();
            }
        };

        if ($this->serverMutex) {
            $this->serverMutex->attempt($callback);
        } else {
            $callback();
        }
    }

    public function getIdentifier(): string
    {
        return sprintf('%s_start', $this->replication);
    }

    protected function makeHealthMonitor(): HealthMonitor
    {
        return make(HealthMonitor::class, ['replication' => $this]);
    }

    protected function makeServerMutex(): ServerMutexInterface
    {
        return make(ServerMutexInterface::class, [
            'name' => 'trigger:mutex:' . $this->replication,
            'owner' => Util::getInternalIp(),
            'options' => $this->getOption('server_mutex', []) + ['replication' => $this->replication],
        ]);
    }

    protected function makeReplication(): MySQLReplicationFactory
    {
        $replication = $this->replication;
        // Get options
        $config = (array) $this->options;
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

        if ($binLogCurrent = $this->getBinLogCurrentSnapshot()->get()) {
            $configBuilder->withBinLogFileName($binLogCurrent->getBinFileName());
            $configBuilder->withBinLogPosition((int) $binLogCurrent->getBinLogPosition());

            $this->debug('Continue with position', $binLogCurrent->jsonSerialize());
        }

        $eventDispatcher = make(EventDispatcher::class);

        return tap(make(MySQLReplicationFactory::class, [
            'config' => $configBuilder->build(),
            'eventDispatcher' => $eventDispatcher,
        ]), function ($factory) use ($replication) {
            /** @var MySQLReplicationFactory $factory */
            $subscribers = $this->subscriberManager->get($replication);
            $subscribers[] = TriggerSubscriber::class;
            $subscribers[] = SnapshotSubscriber::class;

            foreach ($subscribers as $subscriber) {
                $factory->registerSubscriber(make($subscriber, ['replication' => $this]));
            }
        });
    }

    protected function makeBinLogCurrentSnapshot(): BinLogCurrentSnapshotInterface
    {
        return make(BinLogCurrentSnapshotInterface::class, [
            'replication' => $this,
        ]);
    }

    protected function onReplicationStopped(?BinLogCurrent $binLogCurrent): void
    {
        $this->warning('Replication stopped.');
    }
}
