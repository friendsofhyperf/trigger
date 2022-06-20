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
use Hyperf\Coordinator\Constants;
use Hyperf\Coordinator\CoordinatorManager;
use Hyperf\Redis\Redis;
use Hyperf\Utils\Coroutine;
use Throwable;

class RedisServerMutex implements ServerMutexInterface
{
    use Logger;

    private bool $released = false;

    private $keepaliveTimerId;

    private int $expires = 60;

    private int $keepaliveInterval = 10;

    private int $retryInterval = 10;

    private string $replication = 'default';

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
        $this->replication = $options['replication'];
        $this->owner = $owner ?? Util::getInternalIp();
    }

    public function attempt(callable $callback = null): void
    {
        Coroutine::create(function () {
            while (true) {
                if ($this->redis->set($this->name, $this->owner, ['NX', 'EX' => $this->expires]) || $this->redis->get($this->name) == $this->owner) {
                    $this->debug('Got server mutex.');
                    CoordinatorManager::until($this->getIdentifier())->resume();
                    break;
                }

                $this->debug('Waiting server mutex.');

                sleep($this->retryInterval);
            }
        });

        Coroutine::create(function () {
            CoordinatorManager::until($this->getIdentifier())->yield();

            while (true) {
                $isExited = CoordinatorManager::until(Constants::WORKER_EXIT)->yield($this->keepaliveInterval);

                if ($isExited) {
                    $this->warning('Server mutex exited.');
                    break;
                }

                $this->redis->setNx($this->name, $this->owner);
                $this->redis->expire($this->name, $this->expires);
                $ttl = $this->redis->ttl($this->name);

                $this->debug('Server mutex keepalive executed', ['ttl' => $ttl]);
            }
        });

        CoordinatorManager::until($this->getIdentifier())->yield();

        $this->debug('Server mutex keepalive booted.');

        if ($callback) {
            try {
                $callback();
            } catch (Throwable $e) {
                $this->debug($e->getMessage(), ['position' => $e->getFile() . ':' . $e->getLine()]);
            }
        }
    }

    public function release(bool $force = false): void
    {
        if ($force || $this->redis->get($this->name) == $this->owner) {
            $this->redis->del($this->name);
            $this->released = true;
        }
    }

    protected function getIdentifier(): string
    {
        return sprintf('%s_%s', $this->replication, __CLASS__);
    }
}
