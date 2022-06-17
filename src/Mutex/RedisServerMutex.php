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

use FriendsOfHyperf\Trigger\Process\ConsumeProcess;
use FriendsOfHyperf\Trigger\Traits\Logger;
use FriendsOfHyperf\Trigger\Util;
use Hyperf\Contract\StdoutLoggerInterface;
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
        ConsumeProcess $process
    ) {
        $this->expires = (int) $process->getOption('server_mutex.expires', 60);
        $this->retryInterval = (int) $process->getOption('server_mutex.retry_interval', 10);
        $this->keepaliveInterval = (int) $process->getOption('server_mutex.keepalive_interval', 10);
        $this->replication = $process->getReplication();
        $this->owner = $owner ?? Util::getInternalIp();
    }

    public function attempt(callable $callback = null): void
    {
        Coroutine::create(function () {
            while (true) {
                if ($this->redis->set($this->name, $this->owner, ['NX', 'EX' => $this->expires]) || $this->redis->get($this->name) == $this->owner) {
                    $this->info('Got server mutex.');
                    CoordinatorManager::until(__CLASS__)->resume();
                    break;
                }

                $this->info('Waiting server mutex.');

                sleep($this->retryInterval);
            }
        });

        Coroutine::create(function () {
            CoordinatorManager::until(__CLASS__)->yield();

            while (true) {
                $this->redis->setNx($this->name, $this->owner);
                $this->redis->expire($this->name, $this->expires);
                $ttl = $this->redis->ttl($this->name);

                $this->info('Server mutex keepalive executed', ['ttl' => $ttl]);

                sleep($this->keepaliveInterval);
            }
        });

        CoordinatorManager::until(__CLASS__)->yield();

        $this->info('Server mutex keepalive booted.');

        if ($callback) {
            try {
                $callback();
            } catch (Throwable $e) {
                $this->info($e->getMessage(), ['position' => $e->getFile() . ':' . $e->getLine()]);
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
}
