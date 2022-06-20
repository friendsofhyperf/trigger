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

use FriendsOfHyperf\Trigger\Replication;
use Hyperf\Redis\Redis;
use MySQLReplication\BinLog\BinLogCurrent;

class RedisBinLogCurrentSnapshot implements BinLogCurrentSnapshotInterface
{
    public function __construct(
        private Replication $replication,
        private Redis $redis
    ) {
    }

    public function set(BinLogCurrent $binLogCurrent): void
    {
        $this->redis->set($this->key(), serialize($binLogCurrent));
        $this->redis->expire($this->key(), (int) $this->replication->getOption('snapshot.expires', 24 * 3600));
    }

    public function get(): ?BinLogCurrent
    {
        return with($this->redis->get($this->key()), function ($data) {
            $data = unserialize((string) $data);

            if (! ($data instanceof BinLogCurrent)) {
                return null;
            }

            return $data;
        });
    }

    private function key(): string
    {
        return join(':', [
            'trigger',
            'snapshot',
            'binLogCurrent',
            $this->replication->getOption('snapshot.version', '1.0'),
            $this->replication->getReplication(),
        ]);
    }
}
