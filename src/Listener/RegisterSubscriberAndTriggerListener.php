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

use FriendsOfHyperf\Trigger\SubscriberManager;
use FriendsOfHyperf\Trigger\TriggerManager;
use Hyperf\Event\Annotation\Listener;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\BootApplication;
use Psr\Container\ContainerInterface;

/**
 * @Listener
 */
#[Listener]
class RegisterSubscriberAndTriggerListener implements ListenerInterface
{
    private \FriendsOfHyperf\Trigger\SubscriberManager $subscriberManager;

    private \FriendsOfHyperf\Trigger\TriggerManager $triggerManager;

    public function __construct(ContainerInterface $container)
    {
        $this->subscriberManager = $container->get(SubscriberManager::class);
        $this->triggerManager = $container->get(TriggerManager::class);
    }

    public function listen(): array
    {
        return [
            BootApplication::class,
        ];
    }

    /**
     * @param BootApplication $event
     */
    public function process(object $event)
    {
        $this->subscriberManager->register();
        $this->triggerManager->register();
    }
}
