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
use Swoole\Coroutine\System;
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
     * @var bool
     */
    protected $debug = true;

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
                $this->info('got mutex');
                break;
            }

            $this->info('waiting mutex');

            // System::wait(1);
            sleep(1);
        }

        try {
            // keepalive
            Coroutine::create(function () use ($mutexName, $mutexExpires) {
                $this->info('keepalive start');

                while (true) {
                    if ($this->isStopped()) {
                        $this->info('keepalive stopped');
                        break;
                    }

                    $this->info('keepalive executing');
                    $this->redis->expire($mutexName, $mutexExpires);
                    $this->info(sprintf('keepalive executed [ttl=%s]', $this->redis->ttl($mutexName)));

                    // System::wait(1);
                    sleep(1);
                }
            });

            // run
            $this->info('replication running');
            $this->run();
            $this->info('replication exited');
        } catch (Throwable $e) {
            $this->info(sprintf('replication exited, error:%s', $e->getMessage()));
        } finally {
            // release
            $this->redis->del($this->getMutexName());
            $this->setStopped(true);
            $this->info('mutex released');
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

        foreach ($subscribers as $subscriber) {
            $replication->registerSubscriber($subscriber);
            $this->logger->info(sprintf('[trigger.%s] %s registered by %s process by %s.', $this->replication, get_class($this), get_class($subscriber), get_class($this)));
        }

        // monitor
        $this->isMonitor() && Coroutine::create(function () {
            sleep(3);

            /** @var null|BinLogCurrent $binLogCache */
            $binLogCache = null;
            $position = $this->positionFactory->get($this->replication);

            $this->info('monitor start');

            while (true) {
                if ($this->isStopped()) {
                    $this->info('monitor stopped');
                    break;
                }

                $this->info('monitor executing');

                $binLogCurrent = $position->get();

                if (! ($binLogCurrent instanceof BinLogCurrent)) {
                    $this->info('$binLogCurrent not instanceof BinLogCurrent');
                    sleep(1);
                    continue;
                }

                if (($binLogCache instanceof BinLogCurrent)) {
                    if ($binLogCurrent->getBinLogPosition() == $binLogCache->getBinLogPosition()) {
                        $this->onReplicationStopped($binLogCurrent);
                    }
                }

                $binLogCache = $binLogCurrent;

                sleep(3);
            }
        });

        $replication->run();
    }

    protected function isMonitor(): bool
    {
        if (isset($this->monitor) && is_bool($this->monitor)) {
            return $this->monitor;
        }

        return (bool) $this->config->get(sprintf('trigger.%s.monitor', $this->replication), false);
    }

    protected function onReplicationStopped(BinLogCurrent $binLogCurrent): void
    {
        $this->info(sprintf('replication stopped, binlogFileName:%s, binlogPosition:%s', $binLogCurrent->getBinFileName(), $binLogCurrent->getBinLogPosition()));
    }

    protected function info(string $message = '', array $context = [])
    {
        $message = sprintf(
            '[trigger.%s] %s by %s. %s',
            $this->replication,
            $message,
            get_class($this),
            json_encode($context, JSON_UNESCAPED_UNICODE)
        );

        if (! $this->debug) {
            return;
        }

        if ($this->logger) {
            $this->logger->info($message);
        } else {
            echo $message, "\n";
        }
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
