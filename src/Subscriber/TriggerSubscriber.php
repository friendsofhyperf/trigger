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

use FriendsOfHyperf\Trigger\TriggerManager;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\StdoutLoggerInterface;
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
    protected $chan;

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

    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var StdoutLoggerInterface
     */
    protected $logger;

    public function __construct(ContainerInterface $container, string $replication = 'default')
    {
        $this->replication = $replication;
        $this->container = $container;
        $this->config = $container->get(ConfigInterface::class);
        $this->triggerManager = $container->get(TriggerManager::class);
        $this->logger = $container->get(StdoutLoggerInterface::class);
        $this->chan = new Channel(1000);
        $this->concurrent = new Concurrent(
            (int) $this->config->get(sprintf('trigger.%s.trigger.current', $replication), 1000)
        );

        Coroutine::create(function () {
            while (true) {
                /** @var EventDTO $event */
                $event = $this->chan->pop();

                if ($event instanceof EventDTO) {
                    $this->consume($event);
                }
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
        $this->chan->push($event);
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
                    [$class, $method] = $callable;

                    if (! $this->container->has($class)) {
                        $this->logger->warning(sprintf('Entry "%s" cannot be resolved.', $class));
                        return;
                    }

                    switch ($eventType) {
                        case ConstEventsNames::WRITE:
                            $args = [$value];
                            break;
                        case ConstEventsNames::UPDATE:
                            $args = [$value['before'], $value['after']];
                            break;
                        case ConstEventsNames::DELETE:
                            $args = [$value];
                            break;
                        default:
                            return;
                    }

                    call([$this->container->get($class), $method], $args);
                });
            }
        }
    }
}
