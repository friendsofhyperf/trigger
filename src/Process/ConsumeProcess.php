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
use FriendsOfHyperf\Trigger\Util;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Process\AbstractProcess;
use Psr\Container\ContainerInterface;

class ConsumeProcess extends AbstractProcess
{
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
    private $stopped = false;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->name = 'trigger.' . $this->replication;
        $this->logger = $container->get(StdoutLoggerInterface::class);
        $this->replicationFactory = $container->get(ReplicationFactory::class);

        if ($this->onOneServer) {
            $this->mutex = make(ServerMutexInterface::class, [
                'process' => $this,
            ]);
        }
    }

    public function handle(): void
    {
        $callback = function () {
            $this->replicationFactory
                ->make($this->replication)
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
