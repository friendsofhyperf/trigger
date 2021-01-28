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
     * @var bool
     */
    protected $debug = true;

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
    protected $mutexExpires = 60;

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
        while (true) {
            // get lock
            if ((bool) $this->redis->set($this->getMutexName(), $this->getInternalIp(), ['NX', 'EX' => $this->getMutexExpires()])) {
                $this->info('got mutex');
                break;
            }

            $this->info('waiting mutex');

            System::wait(1);
        }

        try {
            // keepalive
            Coroutine::create(function () {
                while (true) {
                    $this->redis->expire($this->getMutexName(), $this->getMutexExpires());
                    $this->info(sprintf('keepalive running [ttl=%s]', $this->redis->ttl($this->getMutexName())));

                    // System::wait(1);
                    sleep(1);
                }
            });

            // run
            $this->info('running');
            $this->run();
            $this->info('exited');
        } catch (Throwable $e) {
            $this->info(sprintf('exit, error:%s', $e->getMessage()));
        } finally {
            // release
            $this->redis->del($this->getMutexName());
            $this->info('release mutex');
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
