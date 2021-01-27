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

use FriendsOfHyperf\Trigger\ReplicationFactory;
use FriendsOfHyperf\Trigger\Subscriber\HeartbeatSubscriber;
use FriendsOfHyperf\Trigger\Subscriber\TriggerSubscriber;
use FriendsOfHyperf\Trigger\SubscriberProviderFactory;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Process\AbstractProcess;
use Hyperf\Redis\Redis;
use Hyperf\Utils\Arr;
use Hyperf\Utils\Coroutine;
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
     * @var string
     */
    protected $replication = 'default';

    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var int
     */
    protected $mutexExpires = 5;

    /**
     * @var bool
     */
    protected $onOneServer = false;

    /**
     * @var Redis
     */
    protected $redis;

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

    /**
     * @var StdoutLoggerInterface
     */
    protected $logger;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->subscriberProviderFactory = $container->get(SubscriberProviderFactory::class);
        $this->replicationFactory = $container->get(ReplicationFactory::class);
        $this->logger = $container->get(StdoutLoggerInterface::class);
        $this->redis = $container->get(Redis::class);
        $this->config = $container->get(ConfigInterface::class);

        $config = $container->get(ConfigInterface::class)->get('trigger.' . $this->replication);

        $this->name = "trigger.{$this->replication}";
        $this->nums = $config['processes'] ?? 1;
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
        while (true) {
            // get lock
            if ((bool) $this->redis->set($this->getMutexName(), $this->getMacAddress(), ['NX', 'EX' => $this->getMutexExpires()])) {
                $this->logger->debug(sprintf('⚠️[trigger.%s] got mutex by %s.', $this->replication, get_class($this)));
                break;
            }

            $this->logger->debug(sprintf('⚠️[trigger.%s] waiting mutex by %s.', $this->replication, get_class($this)));
            Coroutine::sleep(3);
        }

        try {
            // keepalive
            Coroutine::create(function () {
                while (true) {
                    $this->redis->expire($this->getMutexName(), $this->getMutexExpires());
                    $this->logger->debug(sprintf('⚠️[trigger.%s] ttl=%s by %s.', $this->replication, $this->redis->ttl($this->getMutexName()), get_class($this)));
                    $this->logger->debug(sprintf('⚠️[trigger.%s] keepalive running by %s.', $this->replication, get_class($this)));

                    if ($this->isStopped()) {
                        $this->logger->debug(sprintf('⚠️[trigger.%s] keepalive exited by %s.', $this->replication, get_class($this)));
                        break;
                    }

                    Coroutine::sleep(3);
                }
            });

            // wait signal
            foreach ([SIGTERM, SIGINT] as $signal) {
                Coroutine::create(function () use ($signal) {
                    $this->logger->debug(sprintf('⚠️[trigger.%s] listen signal[%s] by %s.', $this->replication, $signal, get_class($this)));

                    while (true) {
                        $ret = System::waitSignal($signal, $this->config->get('signal.timeout', 5.0));

                        if ($ret) {
                            $this->setStopped(true);
                        }

                        if ($this->isStopped()) {
                            $this->logger->debug(sprintf('⚠️[trigger.%s] stopped by %s.', $this->replication, get_class($this)));
                            break;
                        }
                    }
                });
            }

            // run
            $this->logger->debug(sprintf('⚠️[trigger.%s] running by %s.', $this->replication, get_class($this)));
            $this->run();
            $this->logger->debug(sprintf('⚠️[trigger.%s] exited by %s.', $this->replication, get_class($this)));
        } catch (Throwable $e) {
            $this->logger->warning(sprintf('⚠️[trigger.%s] exit, error:% by %s.', $this->replication, get_class($this), $e->getMessage()));
        } finally {
            // release
            $this->redis->del($this->getMutexName());
            $this->logger->debug(sprintf('⚠️[trigger.%s] release mutex by %s.', $this->replication, get_class($this)));
        }
    }

    public function setStopped(bool $stopped): self
    {
        $this->stopped = $stopped;

        return $this;
    }

    public function isStopped(): bool
    {
        return (bool) $this->stopped;
    }

    public function getMutexName(): string
    {
        return 'trigger:mutex:' . $this->replication;
    }

    public function getMutexExpires(): int
    {
        return (int) $this->mutexExpires;
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

        $replication->run();
    }

    protected function getMacAddress(): ?string
    {
        $macAddresses = swoole_get_local_mac();

        foreach (Arr::wrap($macAddresses) as $name => $address) {
            if ($address && $address !== '00:00:00:00:00:00') {
                return $name . ':' . str_replace(':', '', $address);
            }
        }

        return null;
    }
}
