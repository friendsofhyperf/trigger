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
use MySQLReplication\Config\ConfigBuilder;
use MySQLReplication\MySQLReplicationFactory;

class Replication
{
    use Logger;

    protected ?string $name;

    private ?HealthMonitor $healthMonitor;

    private ?ServerMutexInterface $serverMutex;

    private BinLogCurrentSnapshotInterface $binLogCurrentSnapshot;

    private bool $stopped = false;

    public function __construct(
        protected subscriberManager $subscriberManager,
        protected TriggerManager $triggerManager,
        protected StdoutLoggerInterface $logger,
        protected string $pool = 'default',
        protected array $options = []
    ) {
        if (isset($options['name'])) {
            $this->name = $options['name'];
        }

        $this->binLogCurrentSnapshot = make(BinLogCurrentSnapshotInterface::class, [
            'replication' => $this,
        ]);

        if ($this->getOption('server_mutex.enable', true)) {
            $this->serverMutex = make(ServerMutexInterface::class, [
                'name' => 'trigger:mutex:' . $this->pool,
                'owner' => Util::getInternalIp(),
                'options' => $this->getOption('server_mutex', []) + ['pool' => $this->pool],
            ]);
        }

        if ($this->getOption('health_monitor.enable', true)) {
            $this->healthMonitor = make(HealthMonitor::class, ['replication' => $this]);
        }
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

    public function getBinLogCurrentSnapshot(): BinLogCurrentSnapshotInterface
    {
        return $this->binLogCurrentSnapshot;
    }

    public function getHealthMonitor(): ?HealthMonitor
    {
        return $this->healthMonitor;
    }

    public function getName(): string
    {
        return $this->name ?? 'trigger-' . $this->pool;
    }

    public function getOption(?string $key = null, $default = null)
    {
        if (is_null($key)) {
            return $this->options;
        }

        return Arr::get($this->options, $key, $default);
    }

    public function getPool(): string
    {
        return $this->pool;
    }

    public function getIdentifier(): string
    {
        return sprintf('%s_start', $this->pool);
    }

    public function stop(): void
    {
        $this->stopped = true;
        $this->serverMutex?->release();
    }

    public function isStopped(): bool
    {
        return $this->stopped;
    }

    protected function makeReplication(): MySQLReplicationFactory
    {
        $pool = $this->pool;
        // Get options
        $config = (array) $this->options;
        // Get databases of replication
        $databasesOnly = array_replace(
            $config['databases_only'] ?? [],
            $this->triggerManager->getDatabases($pool)
        );
        // Get tables of replication
        $tablesOnly = array_replace(
            $config['tables_only'] ?? [],
            $this->triggerManager->getTables($pool)
        );

        /** @var ConfigBuilder */
        $configBuilder = tap(
            new ConfigBuilder(),
            fn (ConfigBuilder $builder) => $builder->withUser($config['user'] ?? 'root')
                ->withHost($config['host'] ?? '127.0.0.1')
                ->withPassword($config['password'] ?? 'root')
                ->withPort((int) $config['port'] ?? 3306)
                ->withSlaveId(random_int(100, 999))
                ->withHeartbeatPeriod((float) $config['heartbeat_period'] ?? 3)
                ->withDatabasesOnly($databasesOnly)
                ->withTablesOnly($tablesOnly)
        );

        if ($binLogCurrent = $this->getBinLogCurrentSnapshot()->get()) {
            $configBuilder->withBinLogFileName($binLogCurrent->getBinFileName());
            $configBuilder->withBinLogPosition((int) $binLogCurrent->getBinLogPosition());

            $this->debug('Continue with position', $binLogCurrent->jsonSerialize());
        }

        $eventDispatcher = make(EventDispatcher::class);

        return tap(make(MySQLReplicationFactory::class, [
            'config' => $configBuilder->build(),
            'eventDispatcher' => $eventDispatcher,
        ]), function ($factory) use ($pool) {
            /** @var MySQLReplicationFactory $factory */
            $subscribers = $this->subscriberManager->get($pool);
            $subscribers[] = TriggerSubscriber::class;
            $subscribers[] = SnapshotSubscriber::class;

            foreach ($subscribers as $subscriber) {
                $factory->registerSubscriber(make($subscriber, ['replication' => $this]));
            }
        });
    }
}
