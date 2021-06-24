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

use FriendsOfHyperf\Trigger\ChannelManager;
use FriendsOfHyperf\Trigger\TriggerManager;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Utils\Coordinator\Constants;
use Hyperf\Utils\Coordinator\CoordinatorManager;
use Hyperf\Utils\Coroutine;
use Hyperf\Utils\Coroutine\Concurrent;
use MySQLReplication\Definitions\ConstEventsNames;
use MySQLReplication\Event\DTO\EventDTO;
use MySQLReplication\Event\DTO\RowsDTO;
use Psr\Container\ContainerInterface;
use Swoole\Coroutine\Channel;

class TriggerSubscriber extends AbstractSubscriber
{
    /**
     * @var Channel
     */
    protected $channel;

    /**
     * @var TriggerManager
     */
    protected $triggerManager;

    /**
     * @var string
     */
    protected $replication;

    /**
     * @var Concurrent
     */
    protected $concurrent;

    /**
     * @var ConfigInterface
     */
    protected $config;

    public function __construct(ContainerInterface $container, string $replication = 'default')
    {
        $this->replication = $replication;
        $this->config = $container->get(ConfigInterface::class);
        $this->channel = $container->get(ChannelManager::class)->get($replication);
        $this->triggerManager = $container->get(TriggerManager::class);
        $this->concurrent = new Concurrent(
            (int) $this->config->get('trigger.' . $replication . '.trigger.current', 1000)
        );

        Coroutine::create(function () {
            CoordinatorManager::until(Constants::WORKER_START)->yield();

            while (true) {
                /** @var EventDTO $event */
                $event = $this->channel->pop();

                $this->consume($event);
            }
        });
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
        $this->channel->push($event);
    }

    protected function consume(RowsDTO $event): void
    {
        $key = join('.', [
            $this->replication,
            $event->getTableMap()->getDatabase(),
            $event->getTableMap()->getTable(),
            $event->getType(),
        ]);

        $eventType = $event->getType();

        foreach ($this->triggerManager->get($key) as $callable) {
            foreach ($event->getValues() as $value) {
                $this->concurrent->create(function () use ($callable, $value, $eventType) {
                    switch ($eventType) {
                        case ConstEventsNames::WRITE:
                            call($callable, [$value]);
                            break;
                        case ConstEventsNames::UPDATE:
                            call($callable, [$value['new'], $value['old']]);
                            break;
                        case ConstEventsNames::DELETE:
                            call($callable, [$value]);
                            break;
                    }
                });
            }
        }
    }
}
