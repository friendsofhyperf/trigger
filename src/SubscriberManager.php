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
use SplPriorityQueue;

class SubscriberManager
{
    private array $subscribers = [];

    public function __construct(private StdoutLoggerInterface $logger)
    {
    }

    public function register()
    {
        /** @var Subscriber[] $classes */
        $classes = AnnotationCollector::getClassesByAnnotation(Subscriber::class);
        $queue = new SplPriorityQueue();

        foreach ($classes as $class => $property) {
            $queue->insert([$class, $property], $property->priority);
        }

        foreach ($queue as $value) {
            [$class, $property] = $value;
            $this->subscribers[$property->replication] ??= [];
            $this->subscribers[$property->replication][] = $class;

            $this->logger->info(sprintf(
                '[trigger.%s] %s registered by %s process by %s.',
                $property->replication,
                $this::class,
                $class,
                $this::class
            ));
        }
    }

    public function get(string $replication = 'default'): array
    {
        return (array) Arr::get($this->subscribers, $replication, []);
    }
}
