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

use FriendsOfHyperf\Trigger\PositionFactory;
use FriendsOfHyperf\Trigger\ReplicationFactory;
use FriendsOfHyperf\Trigger\Subscriber\HeartbeatSubscriber;
use FriendsOfHyperf\Trigger\Subscriber\TriggerSubscriber;
use FriendsOfHyperf\Trigger\SubscriberProviderFactory;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Process\AbstractProcess;
use Hyperf\Redis\Redis;
use Hyperf\Utils\Coroutine;
use MySQLReplication\BinLog\BinLogCurrent;
use Psr\Container\ContainerInterface;
use Throwable;

class ConsumeProcess extends AbstractProcess
{
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
            $this->runOnOnServer();
        }
    }

    public function runOnOnServer(): void
    {
        $mutexName = $this->getMutexName();
        $mutexExpires = $this->getMutexExpires();

        while (true) {
            // get lock
            if ((bool) $this->redis->set($mutexName, $this->getInternalIp(), ['NX', 'EX' => $mutexExpires])) {
                $this->debug('got mutex');
                break;
            }

            $this->debug('waiting mutex');

            sleep(1);
        }

        try {
            // keepalive
            Coroutine::create(function () use ($mutexName, $mutexExpires) {
                $this->debug('keepalive start');

                while (true) {
                    if ($this->isStopped()) {
                        $this->debug('keepalive stopped');
                        break;
                    }

                    $this->debug('keepalive executing');
                    $this->redis->expire($mutexName, $mutexExpires);
                    $this->debug(sprintf('keepalive executed [ttl=%s]', $this->redis->ttl($mutexName)));

                    sleep(1);
                }
            });

            // run
            $this->debug('replication running');
            $this->run();
        } catch (Throwable $e) {
            $this->debug(sprintf('replication exited, error:%s', $e->getMessage()));
        } finally {
            $this->debug('replication exited');
            // release
            $this->redis->del($this->getMutexName());
            $this->setStopped(true);
            $this->debug('mutex released');
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

    public function isStopped(): bool
    {
        return $this->stopped;
    }

    public function setStopped(bool $stopped): self
    {
        $this->stopped = $stopped;

        return $this;
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
        $this->runMonitor();

        // run
        $replication->run();
    }

    protected function isMonitor(): bool
    {
        if (isset($this->monitor) && is_bool($this->monitor)) {
            return $this->monitor;
        }

        return (bool) $this->config->get(sprintf('trigger.%s.monitor', $this->replication), false);
    }

    protected function runMonitor(): void
    {
        if (! $this->isMonitor()) {
            return;
        }

        Coroutine::create(function () {
            $interval = $this->getMonitorInterval();

            sleep($interval);

            /** @var null|BinLogCurrent $binLogCache */
            $binLogCache = null;
            $position = $this->positionFactory->get($this->replication);

            $this->debug('monitor start');

            while (true) {
                if ($this->isStopped()) {
                    $this->debug('monitor stopped');
                    break;
                }

                $this->debug('monitor executing');

                $binLogCurrent = $position->get();

                if (! ($binLogCurrent instanceof BinLogCurrent)) {
                    $this->debug('replication not run yet');
                    sleep($interval);
                    continue;
                }

                if (! ($binLogCache instanceof BinLogCurrent)) {
                    $binLogCache = $binLogCurrent;
                    sleep($interval);
                    continue;
                }

                if ($binLogCurrent->getBinLogPosition() == $binLogCache->getBinLogPosition()) {
                    $this->onReplicationStopped($binLogCurrent);
                }

                $binLogCache = $binLogCurrent;

                $this->debug('monitor executed');

                sleep($interval);
            }
        });
    }

    protected function getMonitorInterval(): int
    {
        return (int) $this->monitorInterval;
    }

    protected function setMonitorInterval(int $seconds): self
    {
        $this->monitorInterval = $seconds;

        return $this;
    }

    protected function onReplicationStopped(BinLogCurrent $binLogCurrent): void
    {
        $this->debug(sprintf('replication stopped, binlogFileName:%s, binlogPosition:%s', $binLogCurrent->getBinFileName(), $binLogCurrent->getBinLogPosition()));
    }

    protected function debug(string $message = '', array $context = []): void
    {
        // $message = sprintf(
        //     '[trigger.%s] %s by %s. %s',
        //     $this->replication,
        //     $message,
        //     get_class($this),
        //     json_encode($context, JSON_UNESCAPED_UNICODE)
        // );

        // if ($this->logger) {
        //     $this->logger->info($message);
        // } else {
        //     echo $message, "\n";
        // }
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
