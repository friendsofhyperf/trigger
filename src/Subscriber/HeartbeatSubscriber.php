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

use FriendsOfHyperf\Trigger\Position;
use FriendsOfHyperf\Trigger\PositionFactory;
use Hyperf\Utils\Coroutine\Concurrent;
use MySQLReplication\Event\DTO\EventDTO;
use Psr\Container\ContainerInterface;

class HeartbeatSubscriber extends AbstractSubscriber
{
    /**
     * @var Position
     */
    protected $position;

    /**
     * @var string
     */
    protected $replication;

    /**
     * @var Concurrent
     */
    protected $concurrent;

    public function __construct(ContainerInterface $container, string $replication = 'default')
    {
        /** @var PositionFactory $factory */
        $factory = $container->get(PositionFactory::class);
        $this->position = $factory->get($replication);
        $this->concurrent = new Concurrent(10);
    }

    protected function allEvents(EventDTO $event): void
    {
        $this->concurrent->create(function () use ($event) {
            $this->position->set($event->getEventInfo()->getBinLogCurrent());
        });
    }
}
