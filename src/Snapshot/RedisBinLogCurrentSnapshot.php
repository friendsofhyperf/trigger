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

use MySQLReplication\BinLog\BinLogCurrent;
use Psr\Container\ContainerInterface;
use Psr\SimpleCache\CacheInterface;

class RedisBinLogCurrentSnapshot implements BinLogCurrentSnapshotInterface
{
    /**
     * @var string
     */
    private $replication;

    /**
     * @var CacheInterface
     */
    private $cache;

    public function __construct(ContainerInterface $container, $replication = 'default')
    {
        $this->replication = $replication;
        $this->cache = $container->get(CacheInterface::class);
    }

    public function set(BinLogCurrent $binLogCurrent): void
    {
        $this->cache->set($this->key(), $binLogCurrent);
    }

    public function get(): ?BinLogCurrent
    {
        $snapshot = $this->cache->get($this->key());

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
