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
use Hyperf\Contract\ConfigInterface;
use Hyperf\Utils\Coroutine\Concurrent;
use MySQLReplication\Definitions\ConstEventsNames;
use MySQLReplication\Event\DTO\EventDTO;
use Psr\Container\ContainerInterface;

class TriggerSubscriber extends AbstractSubscriber
{
    /**
     * @var TriggerDispatcher
     */
    protected $triggerDispatcher;

    /**
     * @var null|Concurrent
     */
    protected $concurrent;

    public function __construct(ContainerInterface $container, string $replication = 'default')
    {
        /** @var TriggerDispatcherFactory $triggerDispatcherFactory */
        $triggerDispatcherFactory = $container->get(TriggerDispatcherFactory::class);
        $this->triggerDispatcher = $triggerDispatcherFactory->get($replication);

        /** @var ConfigInterface $config */
        $config = $container->get(ConfigInterface::class);
        $limit = $config->get(sprintf('trigger.%s.concurrent.limit', $replication), 0);

        if ($limit && is_numeric($limit)) {
            $this->concurrent = new Concurrent((int) $limit);
        }
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
        $this->co(function () use ($event) {
            $this->triggerDispatcher->dispatch($event);
        });
    }

    protected function co(callable $callback): void
    {
        if ($this->concurrent) {
            $this->concurrent->create($callback);
        } else {
            co($callback);
        }
    }
}
