<?php

declare(strict_types=1);
/**
 * This file is part of hyperf-trigger.
 *
 * @link     https://github.com/friendsofhyperf/trigger
 * @document https://github.com/friendsofhyperf/trigger/blob/main/README.md
 * @contact  huangdijia@gmail.com
 */
namespace FriendsOfHyperf\Trigger\Snapshot;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Redis\Redis;
use MySQLReplication\BinLog\BinLogCurrent;
use Psr\Container\ContainerInterface;

class RedisBinLogCurrentSnapshot implements BinLogCurrentSnapshotInterface
{
    private \Redis $redis;

    private \Hyperf\Contract\ConfigInterface $config;

    public function __construct(ContainerInterface $container, private $replication = 'default')
    {
        $this->redis = $container->get(Redis::class);
        $this->config = $container->get(ConfigInterface::class);
    }

    public function set(BinLogCurrent $binLogCurrent): void
    {
        $this->redis->set($this->key(), serialize($binLogCurrent));
        $this->redis->expire($this->key(), (int) $this->config->get(sprintf('trigger.%s.snapshot.expires', $this->replication), 24 * 2600));
    }

    public function get(): ?BinLogCurrent
    {
        return with($this->redis->get($this->key()));
    }

    private function key()
    {
        return join(':', [
            'trigger',
            'snapshot',
            'binLogCurrent',
            $this->config->get(sprintf('trigger.%s.snapshot.version', $this->replication), '1.0'),
            $this->replication,
        ]);
    }
}
