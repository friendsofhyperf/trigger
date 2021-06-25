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

use Hyperf\Redis\Redis;
use MySQLReplication\BinLog\BinLogCurrent;
use Psr\Container\ContainerInterface;

class RedisBinLogCurrentSnapshot implements BinLogCurrentSnapshotInterface
{
    /**
     * @var string
     */
    private $replication;

    /**
     * @var \Redis
     */
    private $redis;

    public function __construct(ContainerInterface $container, $replication = 'default')
    {
        $this->replication = $replication;
        $this->redis = $container->get(Redis::class);
    }

    public function set(BinLogCurrent $binLogCurrent): void
    {
        $this->redis->set($this->key(), $binLogCurrent);
    }

    public function get(): ?BinLogCurrent
    {
        $snapshot = $this->redis->get($this->key());

        if ($snapshot instanceof BinLogCurrent) {
            return $snapshot;
        }

        return null;
    }

    private function key()
    {
        return join(':', [
            'trigger',
            'bin_log_current_snap_shot',
            $this->replication,
        ]);
    }
}
