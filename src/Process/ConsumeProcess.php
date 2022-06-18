<?php

declare(strict_types=1);
/**
 * This file is part of hyperf-trigger.
 *
 * @link     https://github.com/friendsofhyperf/trigger
 * @document https://github.com/friendsofhyperf/trigger/blob/main/README.md
 * @contact  huangdijia@gmail.com
 */
namespace FriendsOfHyperf\Trigger\Process;

use FriendsOfHyperf\Trigger\Monitor\HealthMonitor;
use FriendsOfHyperf\Trigger\Mutex\ServerMutexInterface;
use FriendsOfHyperf\Trigger\ReplicationFactory;
use FriendsOfHyperf\Trigger\Snapshot\BinLogCurrentSnapshotInterface;
use FriendsOfHyperf\Trigger\Traits\Logger;
use FriendsOfHyperf\Trigger\Util;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Coordinator\Constants;
use Hyperf\Coordinator\CoordinatorManager;
use Hyperf\Process\AbstractProcess;
use Hyperf\Utils\Arr;
use Hyperf\Utils\Coroutine;
use MySQLReplication\BinLog\BinLogCurrent;
use Psr\Container\ContainerInterface;

class ConsumeProcess extends AbstractProcess
{
    use Logger;

    protected string $replication = 'default';

    private BinLogCurrentSnapshotInterface $binLogCurrentSnapshot;

    private ?HealthMonitor $healthMonitor;

    private ?ServerMutexInterface $serverMutex;

    private array $options;

    private bool $stopped = false;

    public function __construct(
        ContainerInterface $container,
        protected StdoutLoggerInterface $logger,
        protected ReplicationFactory $replicationFactory
    ) {
        parent::__construct($container);

        $this->name = 'trigger.' . $this->replication;
        $this->options = (array) $container->get(ConfigInterface::class)->get('trigger.' . $this->replication, []);

        $this->binLogCurrentSnapshot = make(BinLogCurrentSnapshotInterface::class, [
            'process' => $this,
            'replication' => $this->replication,
        ]);

        if ($this->getOption('server_mutex.enable', true)) {
            $this->serverMutex = make(ServerMutexInterface::class, [
                'process' => $this,
                'name' => 'trigger:mutex:' . $this->replication,
                'owner' => Util::getInternalIp(),
            ]);
        }

        if ($this->getOption('health_monitor.enable', true)) {
            $this->healthMonitor = make(HealthMonitor::class, ['process' => $this]);
        }
    }

    public function handle(): void
    {
        $callback = function () {
            // Health monitor
            if ($this->healthMonitor) {
                $this->healthMonitor->process();
            }

            // Worker exit
            Coroutine::create(function () {
                CoordinatorManager::until(Constants::WORKER_EXIT)->yield();

                $this->stop();

                $this->warning('Process stopped.');
            });

            $replication = $this->replicationFactory->make($this);

            // Replication start
            CoordinatorManager::until(self::class)->resume();

            $this->info('Process started.');

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

    public function getName(): string
    {
        return $this->name;
    }

    public function getReplication(): string
    {
        return $this->replication;
    }

    public function getHealthMonitor(): ?HealthMonitor
    {
        return $this->healthMonitor;
    }

    public function getBinLogCurrentSnapshot(): BinLogCurrentSnapshotInterface
    {
        return $this->binLogCurrentSnapshot;
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

    public function getOption(?string $key = null, $default = null)
    {
        if (is_null($key)) {
            return $this->options;
        }

        return Arr::get($this->options, $key, $default);
    }

    protected function onReplicationStopped(?BinLogCurrent $binLogCurrent): void
    {
        $this->warning('Replication stopped.');
    }
}
