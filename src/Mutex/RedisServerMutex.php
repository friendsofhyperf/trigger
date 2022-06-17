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

use FriendsOfHyperf\Trigger\Traits\Logger;
use FriendsOfHyperf\Trigger\Util;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Redis\Redis;
use Psr\Container\ContainerInterface;
use Swoole\Timer;
use Throwable;

class RedisServerMutex implements ServerMutexInterface
{
    use Logger;

    /**
     * @var \Redis
     */
    protected $redis;

    protected StdoutLoggerInterface $logger;

    private string $owner;

    private bool $released = false;

    private $keepaliveTimerId;

    public function __construct(
        ContainerInterface $container,
        private string $name,
        private int $expires = 60,
        ?string $owner = null,
        private int $keepaliveInterval = 10,
        private int $retryInterval = 10,
        private string $replication = 'default'
    ) {
        $this->redis = $container->get(Redis::class);
        $this->logger = $container->get(StdoutLoggerInterface::class);
        $this->owner = $owner ?? Util::getInternalIp();
    }

    public function attempt(callable $callback = null)
    {
        while (true) {
            if (
                $this->redis->set($this->name, $this->owner, ['NX', 'EX' => $this->expires])
                || $this->redis->get($this->name) == $this->owner
            ) {
                $this->info('Got server mutex.');
                break;
            }

            $this->info('Waiting server mutex.');

            sleep($this->retryInterval);
        }

        $this->info('Server mutex keepalive booted.');

        $this->keepaliveTimerId = Timer::tick($this->keepaliveInterval * 1000, function () {
            if ($this->released) {
                $this->info('Server mutex released.');
                $this->keepaliveTimerId && Timer::clear($this->keepaliveTimerId);
                return;
            }

            $this->redis->setNx($this->name, $this->owner);
            $this->redis->expire($this->name, $this->expires);
            $ttl = $this->redis->ttl($this->name);

            $this->info('Server mutex keepalive executed', ['ttl' => $ttl]);
        });

        if ($callback) {
            try {
                $callback();
            } catch (Throwable $e) {
                $this->info($e->getMessage(), ['position' => $e->getFile() . ':' . $e->getLine()]);
            }
        }
    }

    public function release(bool $force = false)
    {
        if ($force || $this->redis->get($this->name) == $this->owner) {
            $this->redis->del($this->name);
            $this->released = true;
        }
    }
}
