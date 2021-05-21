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

use FriendsOfHyperf\Trigger\TriggerDispatcher;
use FriendsOfHyperf\Trigger\TriggerDispatcherFactory;
use MySQLReplication\Definitions\ConstEventsNames;
use MySQLReplication\Event\DTO\EventDTO;

class TriggerSubscriber extends AbstractSubscriber
{
    /**
     * @var TriggerDispatcher
     */
    protected $dispatcher;

    public function __construct(TriggerDispatcherFactory $factory, string $replication = 'default')
    {
        $this->dispatcher = $factory->get($replication);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ConstEventsNames::UPDATE => 'onUpdate',
            ConstEventsNames::DELETE => 'onDelete',
            ConstEventsNames::WRITE => 'onWrite',
        ];
    }

    protected function allEvents(EventDTO $event): void
    {
        $this->dispatcher->dispatch($event);
    }
}
