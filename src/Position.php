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

use Hyperf\Contract\ConfigInterface;
use MySQLReplication\BinLog\BinLogCurrent;
use Psr\Container\ContainerInterface;
use Psr\SimpleCache\CacheInterface;

class Position
{
    public const CACHE_TTL = 3600;

    /**
     * @var CacheInterface
     */
    protected $cache;

    /**
     * @var string
     */
    protected $cacheKey;

    public function __construct(ContainerInterface $container, string $replication = '')
    {
        /** @var ConfigInterface $config */
        $config = $container->get(ConfigInterface::class);
        $host = $config->get(sprintf('trigger.%s.host', $replication), '127.0.0.1');
        $this->cacheKey = sprintf('trigger_binlog_current:%s:%s', $replication, $host);
        $this->cache = $container->get(CacheInterface::class);
    }

    public function set(BinLogCurrent $binLogCurrent): void
    {
        $this->cache->set($this->cacheKey, $binLogCurrent, self::CACHE_TTL);
    }

    /**
     * @return null|BinLogCurrent
     */
    public function get()
    {
        $binLogCurrent = $this->cache->get($this->cacheKey);

        if (! ($binLogCurrent instanceof BinLogCurrent)) {
            return null;
        }

        return $binLogCurrent;
    }
}
