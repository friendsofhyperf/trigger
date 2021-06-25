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

interface BinLogCurrentSnapshotInterface
{
    public function set(BinLogCurrent $binLogCurrent): void;

    public function get(): ?BinLogCurrent;
}
