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

class TriggerManagerFactory implements FactoryInterface
{
    /**
     * @var TriggerManager[]
     */
    protected $managers = [];

    /**
     * @return TriggerManager
     */
    public function get(string $replication = 'default')
    {
        if (! isset($this->managers[$replication])) {
            $this->managers[$replication] = new TriggerManager();
        }

        return $this->managers[$replication];
    }
}
