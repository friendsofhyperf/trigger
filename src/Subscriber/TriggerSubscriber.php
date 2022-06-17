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

use FriendsOfHyperf\Trigger\Process\ConsumeProcess;
use FriendsOfHyperf\Trigger\Traits\Logger;
use FriendsOfHyperf\Trigger\TriggerManager;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Utils\Coroutine\Concurrent;
use MySQLReplication\Definitions\ConstEventsNames;
use MySQLReplication\Event\DTO\EventDTO;
use MySQLReplication\Event\DTO\RowsDTO;
use Psr\Container\ContainerInterface;
use Throwable;

class TriggerSubscriber extends AbstractSubscriber
{
    use Logger;

    protected string $replication;

    protected Concurrent $concurrent;

    public function __construct(
        protected ContainerInterface $container,
        protected TriggerManager $triggerManager,
        protected StdoutLoggerInterface $logger,
        ConsumeProcess $process
    ) {
        $this->replication = $process->getReplication();
        $this->concurrent = new Concurrent(
            (int) $process->getOption('concurrent.limit', 1000)
        );
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
        if (! $event instanceof RowsDTO) {
            return;
        }

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
                        $this->warning(sprintf('Entry "%s" cannot be resolved.', $class));
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

                    try {
                        call([$this->container->get($class), $method], $args);
                    } catch (Throwable $e) {
                        $this->logger->error(sprintf(
                            "%s in %s:%s\n%s",
                            $e->getMessage(),
                            $e->getFile(),
                            $e->getLine(),
                            $e->getTraceAsString()
                        ));
                    }
                });
            }
        }
    }
}
