<?php

declare(strict_types=1);
/**
 * This file is part of hyperf-trigger.
 *
 * @link     https://github.com/friendsofhyperf/trigger
 * @document https://github.com/friendsofhyperf/trigger/blob/main/README.md
 * @contact  huangdijia@gmail.com
 */
namespace FriendsOfHyperf\Trigger\Event;

use MySQLReplication\BinLog\BinLogCurrent;

class OnReplicationStop
{
    public function __construct(public string $pool, public ?BinLogCurrent $binLogCurrent = null)
    {
    }
}
