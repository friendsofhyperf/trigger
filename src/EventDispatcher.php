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

use FriendsOfHyperf\Trigger\Process\ConsumeProcess;
use Hyperf\Utils\Coroutine\Concurrent;

class EventDispatcher extends \Symfony\Component\EventDispatcher\EventDispatcher
{
    /**
     * @var ConsumeProcess
     */
    protected $process;

    /**
     * @var Concurrent
     */
    protected $concurrent;

    public function __construct(ConsumeProcess $process = null)
    {
        parent::__construct();

        $this->process = $process;
        $this->concurrent = new Concurrent(1000);
    }

    public function dispatch(object $event, ?string $eventName = null): object
    {
        $this->concurrent->create(function () use ($event, $eventName) {
            parent::dispatch($event, $eventName);
        });

        $this->concurrent->create(function () use ($event) {
            $this->process->getHealthMonitor()->setBinLogCurrent($event->getEventInfo()->getBinLogCurrent());
        });

        return $event;
    }
}
