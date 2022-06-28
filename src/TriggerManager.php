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

use FriendsOfHyperf\Trigger\Annotation\Trigger;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Di\Annotation\AnnotationCollector;
use Hyperf\Utils\Arr;
use SplPriorityQueue;

class TriggerManager
{
    private array $triggers = [];

    public function __construct(private ConfigInterface $config)
    {
    }

    public function register(): void
    {
        /** @var Trigger[] $classes */
        $classes = AnnotationCollector::getClassesByAnnotation(Trigger::class);
        $queue = new SplPriorityQueue();

        foreach ($classes as $class => $property) {
            if ($property->events == ['*']) {
                $property->events = ['write', 'update', 'delete'];
            }
            $queue->insert([$class, $property], $property->priority);
        }

        foreach ($queue as $value) {
            [$class, $property] = $value;

            /** @var Trigger $property */
            foreach ($property->events as $eventType) {
                $config = $this->config->get('trigger.' . $property->replication);
                $property->table ??= class_basename($class);
                $property->database ??= $config['databases_only'][0] ?? '';

                $key = $this->buildKey($property->replication, $property->database, $property->table, $eventType);
                $method = 'on' . ucfirst($eventType);

                $items = Arr::get($this->triggers, $key, []);
                $items[] = [$class, $method];

                Arr::set($this->triggers, $key, $items);
            }
        }
    }

    public function get(string $key): array
    {
        return Arr::get($this->triggers, $key, []);
    }

    public function getDatabases(string $replication): array
    {
        return array_keys($this->get($replication));
    }

    public function getTables(string $replication): array
    {
        $tables = [];

        foreach ($this->getDatabases($replication) as $database) {
            $tables = [...$tables, ...array_keys($this->get($this->buildKey($replication, $database)))];
        }

        return $tables;
    }

    private function buildKey(...$arguments): string
    {
        return join('.', $arguments);
    }
}
