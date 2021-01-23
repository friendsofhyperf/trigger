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

use FriendsOfHyperf\Trigger\Subscriber\AbstractSubscriber;
use SplPriorityQueue;

class SubscriberProvider
{
    /**
     * @var array[]
     */
    protected $subscribers;

    /**
     * @param AbstractSubscriber $subscriber
     */
    public function register($subscriber, int $priority = 1)
    {
        $this->subscribers[] = [$subscriber, $priority];
    }

    /**
     * @return AbstractSubscriber[]
     */
    public function getSubscribers()
    {
        $queue = new SplPriorityQueue();

        foreach ($this->subscribers as [$subscriber, $priority]) {
            $queue->insert($subscriber, $priority);
        }

        return $queue;
    }
}
