<?php

declare(strict_types=1);
/**
 * This file is part of hyperf-trigger.
 *
 * @link     https://github.com/friendsofhyperf/trigger
 * @document https://github.com/friendsofhyperf/trigger/blob/main/README.md
 * @contact  huangdijia@gmail.com
 */
namespace FriendsOfHyperf\Trigger\Listener;

use FriendsOfHyperf\Trigger\Annotation\Trigger;
use FriendsOfHyperf\Trigger\Constact\TriggerInterface;
use FriendsOfHyperf\Trigger\TriggerManagerFactory;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Di\Annotation\AnnotationCollector;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\BootApplication;
use Hyperf\Utils\ApplicationContext;
use SplPriorityQueue;

class RegisterTriggerListener implements ListenerInterface
{
    /**
     * @return string[] returns the events that you want to listen
     */
    public function listen(): array
    {
        return [
            BootApplication::class,
        ];
    }

    /**
     * Handle the Event when the event is triggered, all listeners will
     * complete before the event is returned to the EventDispatcher.
     */
    public function process(object $event)
    {
        if (ApplicationContext::hasContainer()) {
            /** @var TriggerManagerFactory $factory */
            $factory = ApplicationContext::getContainer()->get(TriggerManagerFactory::class);
            $triggers = AnnotationCollector::getClassesByAnnotation(Trigger::class);
            $logger = ApplicationContext::getContainer()->get(StdoutLoggerInterface::class);
            $queue = new SplPriorityQueue();

            foreach ($triggers as $class => $property) {
                $queue->insert([$class, $property], $property->priority ?? 0);
            }

            foreach ($queue as $item) {
                [$class, $property] = $item;

                if (! in_array(TriggerInterface::class, class_implements($class))) {
                    continue;
                }

                if (count($property->events) == 0) {
                    continue;
                }

                $factory->get($property->replication ?: 'default')->register($property->table, $property->events, $class);

                $logger->debug(sprintf('[trigger] %s [replication:%s events:%s] registered by %s listener.', $class, $property->replication, implode(',', $property->events), __CLASS__));
            }
        }
    }
}
