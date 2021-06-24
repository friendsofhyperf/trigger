<?php

declare(strict_types=1);
/**
 * This file is part of hyperf-trigger.
 *
 * @link     https://github.com/friendsofhyperf/trigger
 * @document https://github.com/friendsofhyperf/trigger/blob/main/README.md
 * @contact  huangdijia@gmail.com
 */
namespace FriendsOfHyperf\Trigger\Position;

use MySQLReplication\BinLog\BinLogCurrent;

class Position
{
    /**
     * @var null|BinLogCurrent
     */
    protected $binLogCurrent;

    public function set(BinLogCurrent $binLogCurrent):void
    {
        $this->binLogCurrent = $binLogCurrent;
    }

    /**
     * @return null|BinLogCurrent
     */
    public function get()
    {
        return $this->binLogCurrent;
    }
}
