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

use FriendsOfHyperf\Trigger\Replication;
use MySQLReplication\Event\DTO\EventDTO;

class SnapshotSubscriber extends AbstractSubscriber
{
    public function __construct(protected Replication $replication)
    {
    }

    protected function allEvents(EventDTO $event): void
    {
        if (! $this->replication->getHealthMonitor()) {
            return;
        }

        $this->replication->getHealthMonitor()->setBinLogCurrent($event->getEventInfo()->getBinLogCurrent());
    }
}
