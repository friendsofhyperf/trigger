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

use FriendsOfHyperf\Trigger\Mutex\ServerMutexInterface;
use FriendsOfHyperf\Trigger\ReplicationFactory;
use FriendsOfHyperf\Trigger\Snapshot\BinLogCurrentSnapshotInterface;
use FriendsOfHyperf\Trigger\Traits\Logger;
use FriendsOfHyperf\Trigger\Util;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Process\AbstractProcess;
use Hyperf\Utils\Coordinator\Coordinator;
use Hyperf\Utils\Coordinator\CoordinatorManager;
use Hyperf\Utils\Coroutine;
use MySQLReplication\BinLog\BinLogCurrent;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Swoole\Timer;

class ConsumeProcess extends AbstractProcess
{
    use Logger;

    /**
     * @var StdoutLoggerInterface
     */
    protected $logger;

    /**
     * @var string
     */
    protected $replication = 'default';

    /**
     * @var ReplicationFactory
     */
    protected $replicationFactory;

    /**
     * @var bool
     */
    protected $onOneServer = false;

    /**
     * @var null|ServerMutexInterface
     */
    protected $mutex;

    /**
     * @var int
     */
    protected $mutexExpires = 30;

    /**
     * @var int
     */
    protected $mutexRetryInterval = 10;

    /**
     * @var bool
     */
    protected $monitor = false;

    /**
     * @var int
     */
    protected $monitorInterval = 30;

    /**
     * @var bool
     */
    protected $stopped = false;

    /**
     * @var null|BinLogCurrent
     */
    protected $binLogCurrent;

    /**
     * @var BinLogCurrentSnapshotInterface
     */
    protected $binLogCurrentSnapshot;

    /**
     * @var int
     */
    protected $snapShortInterval = 10;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->name = 'trigger.' . $this->replication;
        $this->logger = $container->get(StdoutLoggerInterface::class);
        $this->replicationFactory = $container->get(ReplicationFactory::class);
        $this->binLogCurrentSnapshot = make(BinLogCurrentSnapshotInterface::class, [
            'replication' => $this->replication,
        ]);

        if ($this->onOneServer) {
            $this->mutex = make(ServerMutexInterface::class, [
                'process' => $this,
            ]);
        }
    }

    public function handle(): void
    {
        $callback = function () {
            if ($this->isMonitor()) {
                // Refresh binLogCurrent
                Coroutine::create(function () {
                    $this->getCoordinator()->yield();

                    $this->info('@BinLogCurrent renewer booting.');

                    while (true) {
                        if ($this->isStopped()) {
                            $this->warn('Process stopped.');
                            break;
                        }

                        if ($this->binLogCurrent) {
                            $this->info(sprintf('Monitoring, binLogCurrent: %s', json_encode($this->binLogCurrent->jsonSerialize())));
                        } else {
                            $this->warn('Process not run yet.');
                        }

                        sleep($this->monitorInterval ?? 10);
                    }
                });

                // Health check and set snapshot
                Coroutine::create(function () {
                    $this->getCoordinator()->yield();

                    $this->info('@Health checker booting.');

                    while (true) {
                        if ($this->isStopped()) {
                            $this->warn('Process stopped.');
                            break;
                        }

                        if ($this->binLogCurrent instanceof BinLogCurrent) {
                            if (
                                $this->binLogCurrentSnapshot->get() instanceof BinLogCurrent
                                && $this->binLogCurrentSnapshot->get()->getBinLogPosition() == $this->binLogCurrent->getBinLogPosition()
                            ) {
                                $this->onReplicationStopped();
                            }

                            $this->binLogCurrentSnapshot->set($this->binLogCurrent);
                        }

                        sleep($this->snapShortInterval);
                    }
                });
            }

            $timerId = Timer::after(3000, fn () => $this->getCoordinator()->resume());

            try {
                $replication = $this->replicationFactory->make($this);

                while (1) {
                    if ($this->isStopped()) {
                        break;
                    }

                    $replication->consume();
                }
            } finally {
                Timer::clear($timerId);
                $this->setStopped(true);
            }
        };

        if ($this->mutex) {
            $this->mutex->attempt($callback);
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

    public function getBinLogCurrentSnapshot(): BinLogCurrentSnapshotInterface
    {
        return $this->binLogCurrentSnapshot;
    }

    public function setBinLogCurrent(BinLogCurrent $binLogCurrent): void
    {
        $this->binLogCurrent = $binLogCurrent;
    }

    public function getBinLogCurrent(): BinLogCurrent
    {
        return $this->binLogCurrent;
    }

    public function isMonitor(): bool
    {
        return $this->monitor;
    }

    public function setStopped(bool $stopped): void
    {
        $this->stopped = $stopped;
    }

    public function isStopped(): bool
    {
        return $this->stopped;
    }

    public function getMutexName(): string
    {
        return 'trigger:mutex:' . $this->replication;
    }

    public function getMutexExpires(): int
    {
        return (int) $this->mutexExpires;
    }

    public function getMutexRetryInterval(): int
    {
        return (int) $this->mutexRetryInterval;
    }

    public function getMutexOwner(): string
    {
        return Util::getInternalIp();
    }

    protected function onReplicationStopped(): void
    {
        $this->warn('Replication stopped.');
    }
}
