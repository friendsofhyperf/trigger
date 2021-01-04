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

class SubscriberManagerFactory implements FactoryInterface
{
    /**
     * @var SubscriberManager[]
     */
    protected $managers = [];

    /**
     * @return SubscriberManager
     */
    public function get(string $replication = 'default')
    {
        if (! isset($this->managers[$replication])) {
            $this->managers[$replication] = new SubscriberManager();
        }

        return $this->managers[$replication];
    }
}
