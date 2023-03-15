<?php

declare(strict_types=1);
/**
 * This file is part of hyperf-trigger.
 *
 * @link     https://github.com/friendsofhyperf/trigger
 * @document https://github.com/friendsofhyperf/trigger/blob/main/README.md
 * @contact  huangdijia@gmail.com
 */
namespace FriendsOfHyperf\Trigger\Subscriber;

use FriendsOfHyperf\Trigger\Consumer;
use MySQLReplication\Event\DTO\EventDTO;

class SnapshotSubscriber extends AbstractSubscriber
{
    public function __construct(protected Consumer $consumer)
    {
    }

    protected function allEvents(EventDTO $event): void
    {
        if (! $this->consumer->getHealthMonitor()) {
            return;
        }

        $this->consumer->getHealthMonitor()->setBinLogCurrent($event->getEventInfo()->getBinLogCurrent());
    }
}
