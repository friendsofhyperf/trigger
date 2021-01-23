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

use Hyperf\Contract\ConfigInterface;
use Hyperf\Utils\Coroutine\Concurrent;
use MySQLReplication\Definitions\ConstEventsNames;
use MySQLReplication\Event\DTO\RowsDTO;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

class TriggerDispatcher implements EventDispatcherInterface
{
    /**
     * @var null|Concurrent
     */
    protected $concurrent;

    /**
     * @var TriggerProvider
     */
    protected $triggerProvider;

    public function __construct(TriggerProvider $provider, ContainerInterface $container)
    {
        $this->triggerProvider = $provider;

        /** @var ConfigInterface $config */
        $config = $container->get(ConfigInterface::class);
        $limit = $config->get('trigger.concurrent.limit');

        if ($limit && is_numeric($limit)) {
            $this->concurrent = new Concurrent((int) $limit);
        }
    }

    /**
     * @param RowsDTO $event
     */
    public function dispatch(object $event)
    {
        $listeners = $this->triggerProvider->getListenersForEvent($event);
        $arguments = [];

        foreach ($event->getValues() as $value) {
            switch ($event->getType()) {
                case ConstEventsNames::DELETE:
                    $arguments = [$value];
                    break;
                case ConstEventsNames::UPDATE:
                    $arguments = [$value['before'], $value['after']];
                    break;
                case ConstEventsNames::WRITE:
                    $arguments = [$value];
                    break;
            }

            foreach ($listeners as $trigger) {
                $callback = function () use ($trigger, $arguments) {
                    $trigger(...$arguments);
                };

                if ($this->concurrent) {
                    $this->concurrent->create($callback);
                } else {
                    parallel([$callback]);
                }
            }
        }

        return $event;
    }
}
