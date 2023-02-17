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
use Hyperf\Coordinator\CoordinatorManager;
use Hyperf\Coordinator\Timer;
use Hyperf\Redis\Redis;
use Hyperf\Utils\Coroutine;
use RedisException;
use Throwable;

class RedisServerMutex implements ServerMutexInterface
{
    use Logger;

    protected Timer $timer;

    private bool $released = false;

    private $keepaliveTimerId;

    private int $expires = 60;

    private int $keepaliveInterval = 10;

    private int $retryInterval = 10;

    private string $pool = 'default';

    public function __construct(
        protected StdoutLoggerInterface $logger,
        protected Redis $redis,
        private ?string $name = null,
        protected ?string $owner = null,
        array $options = []
    ) {
        $this->expires = (int) $options['expires'] ?? 60;
        $this->retryInterval = (int) $options['retry_interval'] ?? 10;
        $this->keepaliveInterval = (int) $options['keepalive_interval'] ?? 10;
        $this->pool = $options['pool'];
        $this->owner = $owner ?? Util::getInternalIp();
        $this->timer = new Timer($logger);
    }

    public function attempt(callable $callback = null): void
    {
        // Waiting for the server mutex.
        Coroutine::create(function () {
            $this->timer->tick($this->retryInterval, function () {
                if (
                    $this->redis->set($this->name, $this->owner, ['NX', 'EX' => $this->expires])
                    || $this->redis->get($this->name) == $this->owner
                ) {
                    $this->debug('Got server mutex.');
                    CoordinatorManager::until($this->getIdentifier())->resume();

                    return Timer::STOP;
                }

                $this->debug('Waiting server mutex.');
            });
        });

        // Keepalive the server mutex.
        Coroutine::create(function () {
            CoordinatorManager::until($this->getIdentifier())->yield();

            $this->timer->tick($this->keepaliveInterval, function () {
                if ($this->released) {
                    $this->debug('Server mutex keepalive stopped.');

                    return Timer::STOP;
                }

                $this->redis->setNx($this->name, $this->owner);
                $this->redis->expire($this->name, $this->expires);
                $ttl = $this->redis->ttl($this->name);

                $this->debug('Server mutex keepalive executed', ['ttl' => $ttl]);
            });
        });

        // Waiting for the server mutex.
        CoordinatorManager::until($this->getIdentifier())->yield();

        $this->debug('Server mutex keepalive booted.');

        if ($callback) {
            try {
                $callback();
            } catch (Throwable $e) {
                $this->error($e->getMessage(), ['position' => $e->getFile() . ':' . $e->getLine()]);
            }
        }
    }

    /**
     * Release the server mutex.
     * @throws RedisException
     */
    public function release(bool $force = false): void
    {
        if ($force || $this->redis->get($this->name) == $this->owner) {
            $this->redis->del($this->name);
            $this->released = true;
        }
    }

    protected function getIdentifier(): string
    {
        return sprintf('%s_%s', $this->pool, __CLASS__);
    }
}
