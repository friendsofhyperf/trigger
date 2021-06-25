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
     * @var ConsumeProcess
     */
    protected $process;

    /**
     * @var string
     */
    private $replication;

    public function __construct(ContainerInterface $container, ConsumeProcess $process)
    {
        $this->redis = $container->get(Redis::class);
        $this->logger = $container->get(StdoutLoggerInterface::class);
        $this->process = $process;
        $this->replication = $process->getReplication();
    }

    public function attempt(callable $callback = null)
    {
        $name = $this->process->getMutexName();
        $expires = $this->process->getMutexExpires();
        $owner = $this->process->getMutexOwner();
        $retryInterval = $this->process->getMutexRetryInterval();

        while (true) {
            if (
                $this->redis->set($name, $owner, ['NX', 'EX' => $expires])
                || $this->redis->get($name) == $owner
            ) {
                $this->info('Got mutex.');
                break;
            }

            $this->info('Waiting mutex.');

            sleep($retryInterval);
        }

        Coroutine::create(function () use ($name, $owner, $expires, $retryInterval) {
            $this->info('Keepalive start.');

            while (true) {
                if ($this->process->isStopped()) {
                    $this->info('Keepalive stopped.');
                    break;
                }

                $this->redis->setNx($name, $owner);
                $this->redis->expire($name, $expires);
                $ttl = $this->redis->ttl($name);
                $this->info(sprintf('Keepalive executed [ttl=%s]', $ttl));

                sleep($retryInterval);
            }
        });

        if ($callback) {
            try {
                $this->info('Process start.');
                $callback();
            } catch (Throwable $e) {
                $this->info($e->getMessage(), [$e->getFile() . ':' . $e->getLine()]);
            } finally {
                $this->info('Process stopped.');
                $this->release();
                $this->info('Mutex released.');
            }
        }
    }

    public function release()
    {
        $this->process->setStopped(true);
        $this->redis->del($this->process->getMutexName());
    }
}
