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
use Hyperf\Utils\Coroutine;
use Psr\Container\ContainerInterface;
use Throwable;

class RedisServerMutex implements ServerMutexInterface
{
    use Logger;

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
    private $name;

    /**
     * @var int
     */
    private $expires;

    /**
     * @var string
     */
    private $owner;

    /**
     * @var mixed
     */
    private $interval;

    /**
     * @var bool
     */
    private $released = false;

    public function __construct(ContainerInterface $container, string $name, int $expires = 60, ?string $owner = null, int $interval = 10)
    {
        $this->redis = $container->get(Redis::class);
        $this->logger = $container->get(StdoutLoggerInterface::class);
        $this->name = $name;
        $this->expires = $expires;
        $this->owner = $owner ?? Util::getInternalIp();
        $this->interval = $interval;
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

            sleep($this->interval);
        }

        Coroutine::create(function () {
            $this->info('Server mutex keepalive booted.');

            while (true) {
                if ($this->released) {
                    $this->info('Server mutex released.');
                    break;
                }

                $this->redis->setNx($this->name, $this->owner);
                $this->redis->expire($this->name, $this->expires);
                $ttl = $this->redis->ttl($this->name);
                $this->info('Server mutex keepalive executed', ['ttl' => $ttl]);

                sleep($this->interval);
            }
        });

        if ($callback) {
            try {
                $callback();
            } catch (Throwable $e) {
                $this->info($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
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
