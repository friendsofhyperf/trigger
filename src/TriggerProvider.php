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

use MySQLReplication\Event\DTO\RowsDTO;
use Psr\EventDispatcher\ListenerProviderInterface;
use SplPriorityQueue;

class TriggerProvider implements ListenerProviderInterface
{
    /**
     * @var array[callable,int]
     */
    protected $triggers = [];

    /**
     * @param RowsDTO $event
     * @return callable[]
     */
    public function getListenersForEvent(object $event): iterable
    {
        $key = $this->buildKey($event->getTableMap()->getTable(), $event->getType());
        $triggers = $this->triggers[$key] ?? [];
        $queue = new SplPriorityQueue();

        foreach ($triggers as [$trigger, $priority]) {
            $queue->insert($trigger, $priority);
        }

        return $queue;
    }

    public function on(string $table, string $event, callable $listener, int $priority = 1): void
    {
        $key = $this->buildKey($table, $event);

        if (! isset($this->triggers[$key])) {
            $this->triggers[$key] = [];
        }

        $this->triggers[$key][] = [$listener, $priority];
    }

    protected function buildKey(string $table, string $event): string
    {
        return sprintf('%s@%s', $table, $event);
    }
}
