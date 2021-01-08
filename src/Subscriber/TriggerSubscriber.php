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

use Closure;
use FriendsOfHyperf\Trigger\Constact\TriggerInterface;
use FriendsOfHyperf\Trigger\TriggerManager;
use FriendsOfHyperf\Trigger\TriggerManagerFactory;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Utils\Coroutine\Concurrent;
use MySQLReplication\Definitions\ConstEventsNames;
use MySQLReplication\Event\DTO\DeleteRowsDTO;
use MySQLReplication\Event\DTO\UpdateRowsDTO;
use MySQLReplication\Event\DTO\WriteRowsDTO;
use Psr\Container\ContainerInterface;

class TriggerSubscriber extends AbstractSubscriber
{
    /**
     * @var TriggerManager
     */
    protected $triggerManager;

    /**
     * @var null|Concurrent
     */
    protected $concurrent;

    public function __construct(ContainerInterface $container, string $replication = 'default')
    {
        /** @var TriggerManagerFactory $factory */
        $factory = $container->get(TriggerManagerFactory::class);
        $this->triggerManager = $factory->get($replication);

        /** @var ConfigInterface $config */
        $config = $container->get(ConfigInterface::class);
        $limit = $config->get('trigger.concurrent.limit');

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

    public function onWrite(WriteRowsDTO $event): void
    {
        $this->co(function () use ($event) {
            $triggers = $this->triggerManager->get($event->getTableMap()->getTable(), $event->getType());

            foreach ($triggers as $class) {
                foreach ($event->getValues() as $new) {
                    $this->co(function () use ($class, $new) {
                        /** @var TriggerInterface $trigger */
                        $trigger = make($class);
                        $trigger->onWrite($new);
                    });
                }
            }
        });
    }

    public function onUpdate(UpdateRowsDTO $event): void
    {
        $this->co(function () use ($event) {
            $triggers = $this->triggerManager->get($event->getTableMap()->getTable(), $event->getType());

            foreach ($triggers as $class) {
                foreach ($event->getValues() as $row) {
                    $this->co(function () use ($class, $row) {
                        /** @var TriggerInterface $trigger */
                        $trigger = make($class);
                        $trigger->onUpdate($row['before'], $row['after']);
                    });
                }
            }
        });
    }

    public function onDelete(DeleteRowsDTO $event): void
    {
        $this->co(function () use ($event) {
            $triggers = $this->triggerManager->get($event->getTableMap()->getTable(), $event->getType());

            foreach ($triggers as $class) {
                foreach ($event->getValues() as $old) {
                    $this->co(function () use ($class, $old) {
                        /** @var TriggerInterface $trigger */
                        $trigger = make($class);
                        $trigger->onDelete($old);
                    });
                }
            }
        });
    }

    protected function co(Closure $callback): void
    {
        if ($this->concurrent) {
            $this->concurrent->create($callback);
        } else {
            parallel([$callback]);
        }
    }
}
