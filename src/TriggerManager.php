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
use Psr\Container\ContainerInterface;
use SplPriorityQueue;

class TriggerManager
{
    /**
     * @var array
     */
    private $triggers = [];

    /**
     * @var ConfigInterface
     */
    private $config;

    public function __construct(ContainerInterface $container)
    {
        $this->config = $container->get(ConfigInterface::class);
    }

    public function register(): void
    {
        /** @var Trigger[] */
        $classes = AnnotationCollector::getClassesByAnnotation(Trigger::class);
        $queue = new SplPriorityQueue();

        foreach ($classes as $class => $property) {
            $queue->insert([$class, $property], $property->priority);
        }

        foreach ($queue as $value) {
            [$class, $property] = $value;

            /** @var Trigger $property */
            foreach ($property->events as $eventType) {
                $config = $this->config->get('trigger.' . $property->replication);
                $property->table = $property->table ?? class_basename($class);
                $property->database = $property->database ?? $config['databases_only'][0] ?? '';

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
            $tables = array_merge($tables, array_keys($this->get($this->buildKey($replication, $database))));
        }

        return $tables;
    }

    private function buildKey(...$arguments): string
    {
        return join('.', $arguments);
    }
}
