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
use Hyperf\Utils\Coroutine;
use MySQLReplication\Event\DTO\EventDTO;
use Psr\Container\ContainerInterface;
use Swoole\Coroutine\Channel;

class EventDispatcher extends \Symfony\Component\EventDispatcher\EventDispatcher
{
    /**
     * @var Channel
     */
    protected $eventChan;

    /**
     * @var Channel
     */
    protected $positionChan;

    /**
     * @var ConsumeProcess
     */
    protected $process;

    public function __construct(ContainerInterface $container, ConsumeProcess $process = null)
    {
        parent::__construct();

        $this->process = $process;
        $this->eventChan = new Channel(1000);
        $this->positionChan = new Channel(1000);

        Coroutine::create(function () {
            while (true) {
                if ($this->process->isStopped()) {
                    break;
                }

                [$event, $eventName] = $this->eventChan->pop();
                parent::dispatch($event, $eventName);
            }
        });

        Coroutine::create(function () {
            while (true) {
                if ($this->process->isStopped()) {
                    break;
                }

                [$event] = $this->positionChan->pop();
                if ($event instanceof EventDTO) {
                    $this->process->getPosition()->set($event->getEventInfo()->getBinLogCurrent());
                }
            }
        });
    }

    public function dispatch(object $event, ?string $eventName = null): object
    {
        $this->eventChan->push(func_get_args());
        $this->positionChan->push(func_get_args());

        return $event;
    }
}
