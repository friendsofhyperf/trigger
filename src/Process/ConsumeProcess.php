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
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Coordinator\Coordinator;
use Hyperf\Coordinator\CoordinatorManager;
use Hyperf\Process\AbstractProcess;
use MySQLReplication\BinLog\BinLogCurrent;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Swoole\Timer;
use TypeError;

class ConsumeProcess extends AbstractProcess
{
    use Logger;

    protected string $replication = 'default';

    protected bool $onOneServer = false;

    protected ?ServerMutexInterface $serverMutex;

    protected int $serverMutexExpires = 30;

    protected int $serverMutexKeepaliveInterval = 10;

    protected int $serverMutexRetryInterval = 10;

    protected bool $monitor = false;

    protected ?HealthMonitor $healthMonitor;

    protected int $healthMonitorInterval = 30;

    protected BinLogCurrentSnapshotInterface $binLogCurrentSnapshot;

    protected int $snapShortInterval = 10;

    protected bool $stopped = false;

    public function __construct(ContainerInterface $container, protected StdoutLoggerInterface $logger, protected ReplicationFactory $replicationFactory)
    {
        parent::__construct($container);

        $this->name = 'trigger.' . $this->replication;
        $this->binLogCurrentSnapshot = make(BinLogCurrentSnapshotInterface::class, [
            'replication' => $this->replication,
        ]);

        if ($this->onOneServer) {
            $this->serverMutex = make(ServerMutexInterface::class, [
                'name' => 'trigger:mutex:' . $this->replication,
                'expires' => $this->serverMutexExpires ?? 60,
                'owner' => Util::getInternalIp(),
                'retryInterval' => $this->serverMutexRetryInterval ?? 10,
                'keepaliveInterval' => $this->serverMutexKeepaliveInterval ?? 10,
                'replication' => $this->getReplication(),
            ]);
        }

        if ($this->monitor) {
            $this->healthMonitor = make(HealthMonitor::class, [
                'process' => $this,
                'binLogCurrentSnapshot' => $this->binLogCurrentSnapshot,
                'monitorInterval' => $this->healthMonitorInterval ?? 10,
                'snapShortInterval' => $this->snapShortInterval ?? 10,
            ]);
        }
    }

    public function handle(): void
    {
        $callback = function () {
            // Health monitor
            if ($this->healthMonitor) {
                $this->healthMonitor->process();
            }

            // Boot replication
            $timerId = Timer::after(1000, fn () => $this->getCoordinator()->resume());

            try {
                $replication = $this->replicationFactory->make($this);

                $this->info('Process started.');

                while (1) {
                    if ($this->isStopped()) {
                        break;
                    }

                    $replication->consume();
                }
            } finally {
                Timer::clear($timerId);
                $this->stop();
                $this->warning('Process stopped.');
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

    /**
     * @throws RuntimeException
     */
    public function getCoordinator(): Coordinator
    {
        return CoordinatorManager::until($this->getName());
    }

    /**
     * @return null|HealthMonitor
     */
    public function getHealthMonitor()
    {
        return $this->healthMonitor;
    }

    /**
     * @return BinLogCurrentSnapshotInterface
     */
    public function getBinLogCurrentSnapshot()
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

    /**
     * @deprecated v2.x
     */
    public function setStopped(bool $stopped): void
    {
        $this->stop();
    }

    public function isStopped(): bool
    {
        return $this->stopped;
    }

    public function callOnReplicationStopped($binLogCurrent)
    {
        $this->onReplicationStopped($binLogCurrent);
    }

    /**
     * @param null|BinLogCurrent $binLogCurrent
     * @throws TypeError
     */
    protected function onReplicationStopped($binLogCurrent): void
    {
        $this->warning('Replication stopped.');
    }
}
