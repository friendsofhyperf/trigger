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

use FriendsOfHyperf\Trigger\Annotation\Subscriber;
use FriendsOfHyperf\Trigger\SubscriberManagerFactory;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Di\Annotation\AnnotationCollector;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\BootApplication;
use Hyperf\Utils\ApplicationContext;
use Psr\Container\ContainerInterface;
use SplPriorityQueue;

class RegisterSubsciberListener implements ListenerInterface
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
            /** @var ContainerInterface $container */
            $container = ApplicationContext::getContainer();
            /** @var SubscriberManagerFactory $factory */
            $factory = $container->get(SubscriberManagerFactory::class);
            /** @var array $subscribers */
            $subscribers = AnnotationCollector::getClassesByAnnotation(Subscriber::class);
            /** @var StdoutLoggerInterface $logger */
            $logger = ApplicationContext::getContainer()->get(StdoutLoggerInterface::class);
            $queue = new SplPriorityQueue();

            foreach ($subscribers as $class => $property) {
                $queue->insert([$class, $property], $property->priority ?? 0);
            }

            foreach ($queue as [$class, $property]) {
                $replication = $property->replication ?? 'default';
                $factory->get($replication)->register($class);

                $logger->debug(sprintf('[trigger.%s] %s registered by %s listener.', $replication, $class, __CLASS__));
            }
        }
    }
}
