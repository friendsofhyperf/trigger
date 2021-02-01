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

use FriendsOfHyperf\Trigger\Monitor\ReplicationMonitor;
use FriendsOfHyperf\Trigger\Mutex\RedisServerMutex;
use FriendsOfHyperf\Trigger\PositionFactory;
use FriendsOfHyperf\Trigger\ReplicationFactory;
use FriendsOfHyperf\Trigger\Subscriber\HeartbeatSubscriber;
use FriendsOfHyperf\Trigger\Subscriber\TriggerSubscriber;
use FriendsOfHyperf\Trigger\SubscriberProviderFactory;
use FriendsOfHyperf\Trigger\Traits\Debug;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Process\AbstractProcess;
use Hyperf\Redis\Redis;
use MySQLReplication\BinLog\BinLogCurrent;
use Psr\Container\ContainerInterface;

class ConsumeProcess extends AbstractProcess
{
    use Debug;

    /**
     * @var ConfigInterface
     */
    protected $config;

    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var StdoutLoggerInterface
     */
    protected $logger;

    /**
     * @var int
     */
    protected $mutexExpires = 60;

    /**
     * @var bool
     */
    protected $monitor;

    /**
     * @var int
     */
    protected $monitorInterval = 5;

    /**
     * @var bool
     */
    protected $onOneServer = false;

    /**
     * @var PositionFactory
     */
    protected $positionFactory;

    /**
     * @var Redis
     */
    protected $redis;

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
    protected $stopped = false;

    /**
     * @var SubscriberProviderFactory
     */
    protected $subscriberProviderFactory;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->subscriberProviderFactory = $container->get(SubscriberProviderFactory::class);
        $this->replicationFactory = $container->get(ReplicationFactory::class);
        $this->positionFactory = $container->get(PositionFactory::class);
        $this->logger = $container->get(StdoutLoggerInterface::class);
        $this->redis = $container->get(Redis::class);
        $this->config = $container->get(ConfigInterface::class);
        $this->name = "trigger.{$this->replication}";
    }

    public function handle(): void
    {
        if (! $this->onOneServer) {
            $this->run();
        } else {
            /** @var RedisServerMutex $mutex */
            $mutex = make(RedisServerMutex::class, ['process' => $this]);

            $mutex->attempt(function () {
                $this->run();
            });
        }
    }

    public function getMutexName(): string
    {
        return 'trigger:mutex:' . $this->replication;
    }

    public function getMutexExpires(): int
    {
        return (int) $this->mutexExpires;
    }

    public function getOwner(): string
    {
        return $this->getInternalIp();
    }

    public function getMonitorInterval(): int
    {
        return (int) $this->monitorInterval;
    }

    public function getReplication(): string
    {
        return $this->replication;
    }

    public function isMonitor(): bool
    {
        if (isset($this->monitor) && is_bool($this->monitor)) {
            return $this->monitor;
        }

        return (bool) $this->config->get(sprintf('trigger.%s.monitor', $this->replication), false);
    }

    public function isStopped(): bool
    {
        return $this->stopped;
    }

    public function run(): void
    {
        // prepare subscribers
        $replication = $this->replicationFactory->get($this->replication);
        $subscribers = with(
            $this->subscriberProviderFactory
                ->get($this->replication)
                ->getSubscribers(),
            function ($subscribers) {
                $subscribers[] = make(TriggerSubscriber::class, ['replication' => $this->replication]);
                $subscribers[] = make(HeartbeatSubscriber::class, ['replication' => $this->replication]);

                return $subscribers;
            }
        );

        // register subscribers
        foreach ($subscribers as $subscriber) {
            $replication->registerSubscriber($subscriber);
            $this->logger->info(sprintf('[trigger.%s] %s registered by %s process by %s.', $this->replication, get_class($this), get_class($subscriber), get_class($this)));
        }

        // monitor
        /** @var ReplicationMonitor $monitor */
        $monitor = make(ReplicationMonitor::class, ['process' => $this]);
        $monitor->run(function ($binLogCurrent) {
            $this->onReplicationStopped($binLogCurrent);
        });

        // run
        $replication->run();
    }

    public function setMonitorInterval(int $seconds): self
    {
        $this->monitorInterval = $seconds;

        return $this;
    }

    public function setStopped(bool $stopped): self
    {
        $this->stopped = $stopped;

        return $this;
    }

    protected function onReplicationStopped(BinLogCurrent $binLogCurrent): void
    {
        $this->debug(sprintf('replication stopped, binlogFileName:%s, binlogPosition:%s', $binLogCurrent->getBinFileName(), $binLogCurrent->getBinLogPosition()));
    }

    protected function getInternalIp(): string
    {
        $ips = swoole_get_local_ip();

        if (is_array($ips) && ! empty($ips)) {
            return current($ips);
        }

        /** @var mixed|string $ip */
        $ip = gethostbyname(gethostname());

        if (is_string($ip)) {
            return $ip;
        }

        throw new \RuntimeException('Can not get the internal IP.');
    }
}
