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
use Hyperf\Contract\ConfigInterface;
use Hyperf\Utils\Coroutine\Concurrent;
use MySQLReplication\Definitions\ConstEventsNames;
use MySQLReplication\Event\DTO\HeartbeatDTO;
use Psr\Container\ContainerInterface;

class HeartbeatSubscriber extends AbstractSubscriber
{
    /**
     * @var PositionFactory
     */
    protected $positionFactory;

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
        $this->positionFactory = $container->get(PositionFactory::class);
        $this->replication = $replication;

        /** @var array $config */
        $config = $container->get(ConfigInterface::class)->get('trigger.' . $replication) ?? [];
        $concurrentLimit = $config['concurrent']['limit'] ?? null;

        if ($concurrentLimit && is_numeric($concurrentLimit)) {
            $this->concurrent = new Concurrent((int) $concurrentLimit);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ConstEventsNames::HEARTBEAT => 'onHeartbeat',
        ];
    }

    public function onHeartbeat(HeartbeatDTO $event): void
    {
        $callback = function () use ($event) {
            $this->positionFactory->get($this->replication)->set($event->getEventInfo()->getBinLogCurrent());
        };

        if ($this->concurrent) {
            $this->concurrent->create($callback);
        } else {
            parallel([$callback]);
        }
    }
}
