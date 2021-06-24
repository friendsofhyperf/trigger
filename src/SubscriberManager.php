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

use FriendsOfHyperf\Trigger\Annotation\Subscriber;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Di\Annotation\AnnotationCollector;
use Hyperf\Utils\Arr;
use Psr\Container\ContainerInterface;
use SplPriorityQueue;

class SubscriberManager
{
    private array $container = [];

    /**
     * @var StdoutLoggerInterface
     */
    private $logger;

    public function __construct(ContainerInterface $container)
    {
        $this->logger = $container->get(StdoutLoggerInterface::class);
    }

    public function register()
    {
        /** @var Subscriber[] */
        $classes = AnnotationCollector::getClassesByAnnotation(Subscriber::class);
        $queue = new SplPriorityQueue();

        foreach ($classes as $class => $property) {
            $queue->insert([$class, $property], $property->priority);
        }

        foreach ($queue as $value) {
            [$class, $property] = $value;
            $this->container[$property->replication] = $this->container[$property->replication] ?? [];
            $this->container[$property->replication][] = $class;

            $this->logger->info(sprintf(
                '[trigger.%s] %s registered by %s process by %s.',
                $property->replication,
                get_class($this),
                $class,
                get_class($this)
            ));
        }
    }

    public function get(string $replication = 'default'): array
    {
        return Arr::get($this->container, $replication, []);
    }
}
