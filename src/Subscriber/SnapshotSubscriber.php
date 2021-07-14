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

use FriendsOfHyperf\Trigger\Process\ConsumeProcess;
use MySQLReplication\Event\DTO\EventDTO;

class SnapshotSubscriber extends AbstractSubscriber
{
    /**
     * @var ConsumeProcess
     */
    protected $process;

    public function __construct(ConsumeProcess $process)
    {
        $this->process = $process;
    }

    protected function allEvents(EventDTO $event): void
    {
        $this->process->getHealthMonitor()->setBinLogCurrent($event->getEventInfo()->getBinLogCurrent());
    }
}
