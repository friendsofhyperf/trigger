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

class SubscriberManager
{
    /**
     * @var string[]
     */
    protected $subscribers;

    /**
     * @param string $subscriber
     */
    public function register($subscriber)
    {
        $this->subscribers[] = $subscriber;
    }

    /**
     * @return string[]
     */
    public function get()
    {
        return $this->subscribers ?: [];
    }
}
