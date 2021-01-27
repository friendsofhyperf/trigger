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
use Psr\Container\ContainerInterface;
use Swoole\Coroutine\System;
use Throwable;

class ConsumeProcess extends AbstractProcess
{
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
    protected $mutexExpires = 3;

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
    protected $stopped;

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

        $config = $container->get(ConfigInterface::class)->get('trigger.' . $this->replication);

        $this->name = "trigger.{$this->replication}";
        $this->nums = $config['processes'] ?? 1;
    }

    public function handle(): void
    {
        if (! $this->onOneServer) {
            $this->run();
        }

        $this->runOnOnServer();
    }

    public function runOnOnServer(): void
    {
        while (true) {
            // get lock
            if ((bool) $this->redis->set($this->getMutexName(), $this->getMacAddress(), ['NX', 'EX' => $this->getMutexExpires()])) {
                break;
            }

            $this->logger->info('waiting.');
            sleep(1);
        }

        try {
            // keepalive
            go(function () {
                while (true) {
                    $this->redis->expire($this->getMutexName(), $this->getMutexExpires());
                    $this->logger->info('refresh.');

                    if ($this->isStopped()) {
                        break;
                    }

                    sleep(1);
                }
            });

            // wait signal
            foreach ([SIGTERM, SIGINT] as $signal) {
                go(function () use ($signal) {
                    while (true) {
                        $ret = System::waitSignal($signal, $this->config->get('signal.timeout', 5.0));

                        if ($ret) {
                            $this->setStopped(true);
                        }

                        if ($this->isStopped()) {
                            break;
                        }
                    }
                });
            }

            // run
            $this->logger->info('running.');
            $this->run();
        } catch (Throwable $e) {
            $this->logger->warning('exit, error:' . $e->getMessage());
        } finally {
            // release
            $this->redis->del($this->getMutexName());
            $this->logger->info('release.');
        }
    }

    public function setStopped(bool $stopped): self
    {
        $this->stopped = $stopped;

        return $this;
    }

    public function isStopped(): bool
    {
        return $this->stopped;
    }

    public function getMutexName(): string
    {
        return 'trigger:mutex:' . $this->name;
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
            $this->logger->info(sprintf('[trigger.%s] %s registered by %s process.', $this->replication, get_class($subscriber), get_class($this)));
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
