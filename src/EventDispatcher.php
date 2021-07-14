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
use FriendsOfHyperf\Trigger\Traits\Logger;
use Hyperf\Utils\Coroutine;
use MySQLReplication\Event\DTO\EventDTO;
use Swoole\Coroutine\Channel;

class EventDispatcher extends \Symfony\Component\EventDispatcher\EventDispatcher
{
    use Logger;

    /**
     * @var Channel
     */
    protected $eventChan;

    /**
     * @var null|Channel
     */
    protected $monitorChan;

    /**
     * @var ConsumeProcess
     */
    protected $process;

    public function __construct(ConsumeProcess $process = null)
    {
        parent::__construct();

        $this->process = $process;
        $this->eventChan = new Channel(1000);

        Coroutine::create(function () {
            while (1) {
                if ($this->process->isStopped()) {
                    $this->warn('Process stopped.');
                    break;
                }

                [$event, $eventName] = $this->eventChan->pop();
                parent::dispatch($event, $eventName);
            }
        });

        if ($healthMonitor = $process->getHealthMonitor()) {
            $this->monitorChan = new Channel(1000);

            Coroutine::create(function () use ($healthMonitor) {
                while (1) {
                    if ($this->process->isStopped()) {
                        $this->warn('Process stopped.');
                        break;
                    }

                    [$event] = $this->monitorChan->pop();
                    if ($event instanceof EventDTO) {
                        $healthMonitor->setBinLogCurrent($event->getEventInfo()->getBinLogCurrent());
                    }
                }
            });
        }
    }

    public function dispatch(object $event, ?string $eventName = null): object
    {
        $this->eventChan->push(func_get_args());

        if ($this->monitorChan) {
            $this->monitorChan->push(func_get_args());
        }

        return $event;
    }
}
