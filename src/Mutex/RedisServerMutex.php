<?php

declare(strict_types=1);
/**
 * This file is part of hyperf-trigger.
 *
 * @link     https://github.com/friendsofhyperf/trigger
 * @document https://github.com/friendsofhyperf/trigger/blob/main/README.md
 * @contact  huangdijia@gmail.com
 */
namespace FriendsOfHyperf\Trigger\Mutex;

use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Redis\Redis;
use Hyperf\Utils\Coroutine;
use Psr\Container\ContainerInterface;

class RedisServerMutex implements ServerMutexInterface
{
    /**
     * @var \Redis
     */
    protected $redis;

    /**
     * @var StdoutLoggerInterface
     */
    protected $logger;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var int
     */
    protected $seconds;

    /**
     * @var string
     */
    protected $owner;

    public function __construct(ContainerInterface $container, string $name, int $seconds = 30, ?string $owner = null)
    {
        $this->redis = $container->get(Redis::class);
        $this->logger = $container->get(StdoutLoggerInterface::class);
        $this->name = $name;
        $this->seconds = $seconds;
        $this->owner = $owner ?? uniqid();
    }

    public function attempt(callable $callback = null)
    {
        $name = $this->name;
        $seconds = $this->seconds;
        $owner = $this->owner;

        while (true) {
            if ((bool) $this->redis->set($name, $owner, ['NX', 'EX' => $seconds])) {
                $this->logger->debug('Got mutex');
                break;
            }

            $this->logger->debug('Waiting mutex');

            sleep(5);
        }

        Coroutine::create(function () use ($name, $seconds) {
            $this->logger->debug('Keepalive start');

            while (true) {
                if ($this->process->isStopped()) {
                    $this->logger->debug('Keepalive stopped');
                    break;
                }

                $this->logger->debug('Keepalive executing');
                $this->redis->expire($name, $seconds);
                $ttl = $this->redis->ttl($name);
                $this->logger->debug(sprintf('Keepalive executed [ttl=%s]', $ttl));

                sleep(10);
            }
        });

        if ($callback) {
            try {
                $this->logger->debug('Process running');
                $callback();
            } finally {
                $this->logger->debug('Process exited');
                $this->release();
                $this->logger->debug('Mutex released');
            }
        }
    }

    public function release()
    {
        $this->process->setStopped(true);
        $this->redis->del($this->process->getMutexName());
    }
}
