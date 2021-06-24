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
use FriendsOfHyperf\Trigger\Position\Position;
use FriendsOfHyperf\Trigger\Position\PositionFactory;
use FriendsOfHyperf\Trigger\ReplicationFactory;
use FriendsOfHyperf\Trigger\Traits\Logger;
use FriendsOfHyperf\Trigger\Util;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Process\AbstractProcess;
use Hyperf\Utils\Coroutine;
use Psr\Container\ContainerInterface;

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
    protected $monitorInterval = 10;

    /**
     * @var bool
     */
    protected $stopped = false;

    /**
     * @var Position
     */
    protected $position;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->name = 'trigger.' . $this->replication;
        $this->logger = $container->get(StdoutLoggerInterface::class);
        $this->replicationFactory = $container->get(ReplicationFactory::class);
        $this->position = $container->get(PositionFactory::class)->get($this->replication);

        if ($this->onOneServer) {
            $this->mutex = make(ServerMutexInterface::class, [
                'process' => $this,
            ]);
        }
    }

    public function handle(): void
    {
        $callback = function () {
            $this->isMonitor() && Coroutine::create(function () {
                while (true) {
                    if ($this->isStopped()) {
                        $this->debug('Process stopped.');
                        break;
                    }

                    $binLogCurrent = $this->position->get();

                    if ($binLogCurrent) {
                        $this->debug(sprintf('Monitor executed, binLogCurrent: %s', json_encode($binLogCurrent->jsonSerialize())));
                    } else {
                        $this->debug('Process not run yet.');
                    }

                    sleep($this->monitorInterval ?? 10);
                }
            });

            $this->replicationFactory
                ->make($this)
                ->run();
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

    public function getPosition(): Position
    {
        return $this->position;
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

    public function getMutexRetryInterval()
    {
        return (int) $this->mutexRetryInterval;
    }

    public function getMutexOwner()
    {
        return Util::getInternalIp();
    }
}
