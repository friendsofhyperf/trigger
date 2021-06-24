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

class PositionFactory
{
    /**
     * @var Position[]
     */
    private $container = [];

    public function get(string $replication = 'default'): Position
    {
        if (! isset($this->container[$replication])) {
            $this->container[$replication] = make(Position::class);
        }

        return $this->container[$replication];
    }
}
