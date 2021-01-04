<?php

declare(strict_types=1);
/**
 * This file is part of hyperf-trigger.
 *
 * @link     https://github.com/friendsofhyperf/trigger
 * @document https://github.com/friendsofhyperf/trigger/blob/main/README.md
 * @contact  huangdijia@gmail.com
 */
namespace FriendsOfHyperf\Trigger;

use MySQLReplication\BinLog\BinLogCurrent;
use Psr\Container\ContainerInterface;
use Psr\SimpleCache\CacheInterface;

class Position
{
    const CACHE_KEY_PREFIX = 'trigger_binlog_current:';

    const CACHE_TTL = 3600;

    /**
     * @var string
     */
    protected $replication;

    /**
     * @var CacheInterface
     */
    protected $cache;

    public function __construct(ContainerInterface $container, string $replication = '')
    {
        $this->replication = $replication;
        $this->cache = $container->get(CacheInterface::class);
    }

    public function set(BinLogCurrent $binLogCurrent)
    {
        $this->cache->set(self::CACHE_KEY_PREFIX . $this->replication, $binLogCurrent, self::CACHE_TTL);
    }

    /**
     * @return null|BinLogCurrent
     */
    public function get()
    {
        return $this->cache->get(self::CACHE_KEY_PREFIX . $this->replication) ?: null;
    }
}
