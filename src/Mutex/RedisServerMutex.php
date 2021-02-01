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
     * @var Redis
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
        while (true) {
            if ((bool) $this->redis->set(
                $this->process->getMutexName(),
                $this->process->getOwner(),
                ['NX', 'EX' => $this->process->getMutexExpires()]
            )) {
                $this->debug('got mutex');
                break;
            }

            $this->debug('waiting mutex');

            sleep(1);
        }

        Coroutine::create(function () {
            $this->debug('keepalive start');

            while (true) {
                if ($this->process->isStopped()) {
                    $this->debug('keepalive stopped');
                    break;
                }

                $this->debug('keepalive executing');
                $this->redis->expire($this->process->getMutexName(), $this->process->getMutexExpires());
                $this->debug(sprintf('keepalive executed [ttl=%s]', $this->redis->ttl($this->process->getMutexName())));

                sleep(1);
            }
        });

        if ($callback) {
            try {
                $this->debug('process running');
                $callback();
            } finally {
                $this->debug('process exited');
                $this->release();
                $this->debug('mutex released');
            }
        }
    }

    public function release()
    {
        $this->process->setStopped(true);
        $this->redis->del($this->process->getMutexName());
    }
}
