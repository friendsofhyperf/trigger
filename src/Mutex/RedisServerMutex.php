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
use FriendsOfHyperf\Trigger\Traits\Debug;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Redis\Redis;
use Hyperf\Utils\Coroutine;
use Psr\Container\ContainerInterface;

class RedisServerMutex implements ServerMutexInterface
{
    use Debug;

    /**
     * @var \Redis
     */
    protected $redis;

    /**
     * @var StdoutLoggerInterface
     */
    protected $logger;

    /**
     * @var ConsumeProcess
     */
    protected $process;

    public function __construct(ContainerInterface $container, ConsumeProcess $process)
    {
        $this->redis = $container->get(Redis::class);
        $this->logger = $container->get(StdoutLoggerInterface::class);
        $this->process = $process;
    }

    public function attempt(callable $callback = null)
    {
        $mutexName = $this->process->getMutexName();
        $mutexExpires = $this->process->getMutexExpires();
        $mutexOwner = $this->process->getOwner();

        Coroutine::create(function () {
            $key = 'test';

            $this->redis->set($key, 1);

            while (true) {
                $t1 = microtime(true);
                $this->redis->expire($key, 100);
                $t2 = microtime(true);
                $ttl = $this->redis->ttl($key);
                $t3 = microtime(true);
                var_dump(__METHOD__, $key, [
                    'expire' => $t2 - $t1,
                    'ttl' => $t3 - $t2,
                ]);
                sleep(1);
            }
        });

        while (true) {
            if ((bool) $this->redis->set($mutexName, $mutexOwner, ['NX', 'EX' => $mutexExpires])) {
                $this->debug('Got mutex');
                break;
            }

            $this->debug('Waiting mutex');

            sleep(1);
        }

        Coroutine::create(function () use ($mutexName, $mutexExpires) {
            $this->debug('Keepalive start');

            while (true) {
                if ($this->process->isStopped()) {
                    $this->debug('Keepalive stopped');
                    break;
                }

                $this->debug('Keepalive executing');
                $this->redis->expire($mutexName, $mutexExpires);
                $ttl = $this->redis->ttl($mutexName);
                $this->debug(sprintf('Keepalive executed [ttl=%s]', $ttl));

                sleep(1);
            }
        });

        if ($callback) {
            try {
                $this->debug('Process running');
                $callback();
            } finally {
                $this->debug('Process exited');
                $this->release();
                $this->debug('Mutex released');
            }
        }
    }

    public function release()
    {
        $this->process->setStopped(true);
        $this->redis->del($this->process->getMutexName());
    }
}
