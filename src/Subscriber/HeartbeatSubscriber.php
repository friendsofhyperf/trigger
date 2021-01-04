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

use FriendsOfHyperf\Trigger\PositionFactory;
use MySQLReplication\Definitions\ConstEventsNames;
use MySQLReplication\Event\DTO\HeartbeatDTO;
use Psr\Container\ContainerInterface;

class HeartbeatSubscriber extends AbstractSubscriber
{
    /**
     * @var PositionFactory
     */
    protected $positionFactory;

    public function __construct(ContainerInterface $container, string $replication = 'default')
    {
        parent::__construct($container, $replication);

        $this->replication = $replication;
        $this->positionFactory = $container->get(PositionFactory::class);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ConstEventsNames::HEARTBEAT => 'onHeartbeat',
        ];
    }

    public function onHeartbeat(HeartbeatDTO $event): void
    {
        $this->positionFactory->get($this->replication)->set($event->getEventInfo()->getBinLogCurrent());
    }
}
