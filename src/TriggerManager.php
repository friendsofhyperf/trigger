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

use Hyperf\Utils\Arr;

class TriggerManager
{
    /**
     * @var string[]
     */
    protected $triggers;

    /**
     * @param string|string[] $events
     * @param string $trigger
     */
    public function register(string $table, $events, $trigger)
    {
        foreach ((array) $events as $event) {
            if (! isset($this->triggers[$table])) {
                $this->triggers[$table] = [];
            }

            if (! isset($this->triggers[$table][$event])) {
                $this->triggers[$table][$event] = [];
            }

            $this->triggers[$table][$event][] = $trigger;
        }
    }

    /**
     * @return string[]
     */
    public function get(string $table, string $event)
    {
        return Arr::get($this->triggers, $this->buildKey($table, $event), []);
    }

    /**
     * @return string[]
     */
    public function all()
    {
        return $this->triggers;
    }

    /**
     * @return string
     */
    protected function buildKey(string $table, string $event)
    {
        return sprintf('%s.%s', $table, $event);
    }
}
