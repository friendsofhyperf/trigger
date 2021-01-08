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

use FriendsOfHyperf\Trigger\Constact\TriggerInterface;
use FriendsOfHyperf\Trigger\TriggerManager;
use FriendsOfHyperf\Trigger\TriggerManagerFactory;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Utils\Coroutine\Concurrent;
use MySQLReplication\Definitions\ConstEventsNames;
use MySQLReplication\Event\DTO\DeleteRowsDTO;
use MySQLReplication\Event\DTO\EventDTO;
use MySQLReplication\Event\DTO\RowsDTO;
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

    /**
     * @var string
     */
    protected $replication;

    public function __construct(ContainerInterface $container, string $replication = 'default')
    {
        $this->replication = $replication;

        /** @var TriggerManagerFactory $factory */
        $factory = $container->get(TriggerManagerFactory::class);
        $this->triggerManager = $factory->get($this->replication);

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
            ConstEventsNames::UPDATE => 'onUpdate',
            ConstEventsNames::DELETE => 'onDelete',
            ConstEventsNames::WRITE => 'onWrite',
        ];
    }

    public function onWrite(WriteRowsDTO $event): void
    {
        $callback = function () use ($event) {
            $triggers = $this->getEventTriggers($event);

            foreach ($triggers as $class) {
                foreach ($event->getValues() as $new) {
                    $callback = function () use ($class, $new) {
                        /** @var TriggerInterface $trigger */
                        $trigger = make($class);
                        $trigger->onWrite($new);
                    };

                    if ($this->concurrent) {
                        $this->concurrent->create($callback);
                    } else {
                        parallel([$callback]);
                    }
                }
            }
        };

        if ($this->concurrent) {
            $this->concurrent->create($callback);
        } else {
            parallel([$callback]);
        }
    }

    public function onUpdate(UpdateRowsDTO $event): void
    {
        $callback = function () use ($event) {
            $triggers = $this->getEventTriggers($event);

            foreach ($triggers as $class) {
                foreach ($event->getValues() as $row) {
                    $callback = function () use ($class, $row) {
                        /** @var TriggerInterface $trigger */
                        $trigger = make($class);
                        $trigger->onUpdate($row['before'], $row['after']);
                    };

                    if ($this->concurrent) {
                        $this->concurrent->create($callback);
                    } else {
                        parallel([$callback]);
                    }
                }
            }
        };

        if ($this->concurrent) {
            $this->concurrent->create($callback);
        } else {
            parallel([$callback]);
        }
    }

    public function onDelete(DeleteRowsDTO $event): void
    {
        $callback = function () use ($event) {
            $triggers = $this->getEventTriggers($event);

            foreach ($triggers as $class) {
                foreach ($event->getValues() as $old) {
                    $callback = function () use ($class, $old) {
                        /** @var TriggerInterface $trigger */
                        $trigger = make($class);
                        $trigger->onDelete($old);
                    };

                    if ($this->concurrent) {
                        $this->concurrent->create($callback);
                    } else {
                        parallel([$callback]);
                    }
                }
            }
        };

        if ($this->concurrent) {
            $this->concurrent->create($callback);
        } else {
            parallel([$callback]);
        }
    }

    /**
     * @return string[]
     */
    protected function getEventTriggers(RowsDTO $event)
    {
        $table = $event->getTableMap()->getTable();
        $eventType = $event->getType();

        return $this->triggerManager->get($table, $eventType);
    }

    // protected function allEvents(EventDTO $event): void
    // {
    //     $callback = function () use ($event) {
    //         if (! ($event instanceof RowsDTO)) {
    //             return;
    //         }

    //         $table = $event->getTableMap()->getTable();
    //         $eventType = $event->getType();
    //         $triggers = $this->triggerManager->get($table, $eventType);

    //         foreach ($triggers as $class) {
    //             /** @var TriggerInterface $trigger */
    //             $trigger = make($class);

    //             switch ($eventType) {
    //                 case ConstEventsNames::UPDATE:
    //                     /** @var UpdateRowsDTO $event */
    //                     foreach ($event->getValues() as $row) {
    //                         $callback = function () use ($trigger, $row) {
    //                             $trigger->onUpdate($row['before'], $row['after']);
    //                         };

    //                         if ($this->concurrent) {
    //                             $this->concurrent->create($callback);
    //                         } else {
    //                             parallel([$callback]);
    //                         }
    //                     }
    //                     break;
    //                 case ConstEventsNames::DELETE:
    //                     /** @var DeleteRowsDTO $event */
    //                     foreach ($event->getValues() as $old) {
    //                         $callback = function () use ($trigger, $old) {
    //                             $trigger->onDelete($old);
    //                         };

    //                         if ($this->concurrent) {
    //                             $this->concurrent->create($callback);
    //                         } else {
    //                             parallel([$callback]);
    //                         }
    //                     }
    //                     break;
    //                 case ConstEventsNames::WRITE:
    //                     /** @var WriteRowsDTO $event */
    //                     foreach ($event->getValues() as $new) {
    //                         $callback = function () use ($trigger, $new) {
    //                             $trigger->onWrite($new);
    //                         };

    //                         if ($this->concurrent) {
    //                             $this->concurrent->create($callback);
    //                         } else {
    //                             parallel([$callback]);
    //                         }
    //                     }
    //                     break;
    //             }
    //         }
    //     };

    //     if ($this->concurrent) {
    //         $this->concurrent->create($callback);
    //     } else {
    //         parallel([$callback]);
    //     }
    // }
}
