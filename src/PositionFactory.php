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

use FriendsOfHyperf\Trigger\Constact\FactoryInterface;

class PositionFactory implements FactoryInterface
{
    /**
     * @var array
     */
    protected $positions = [];

    /**
     * @return Position
     */
    public function get(string $replication = 'default')
    {
        if (! isset($this->positions[$replication])) {
            $this->positions[$replication] = make(Position::class, ['replication' => $replication]);
        }

        return $this->positions[$replication];
    }
}
