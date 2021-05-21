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

class TriggerDispatcherFactory
{
    /**
     * @var TriggerProviderFactory
     */
    protected $factory;

    /**
     * @var TriggerDispatcher[]
     */
    protected $dispatchers;

    public function __construct(TriggerProviderFactory $factory)
    {
        $this->factory = $factory;
    }

    public function get(string $replication = 'default'): TriggerDispatcher
    {
        if (! isset($this->dispatchers[$replication])) {
            $provider = $this->factory->get($replication);
            $this->dispatchers[$replication] = make(TriggerDispatcher::class, compact('replication', 'provider'));
        }

        return $this->dispatchers[$replication];
    }
}
