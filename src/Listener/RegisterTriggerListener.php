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

use Hyperf\Event\Annotation\Listener;
use Psr\Container\ContainerInterface;
use FriendsOfHyperf\Trigger\TriggerManager;
use Hyperf\Framework\Event\BootApplication;
use Hyperf\Event\Contract\ListenerInterface;
use FriendsOfHyperf\Trigger\SubscriberManager;

/**
 * @Listener
 * @package FriendsOfHyperf\Trigger\Listener
 */
class RegisterTriggerListener implements ListenerInterface
{
    /**
     * 
     * @var SubscriberManager
     */
    private $subscriberManager;

    /**
     * 
     * @var TriggerManager
     */
    private $triggerManager;

    public function __construct(ContainerInterface $container, )
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

    public function process(object $event)
    {
        $this->subscriberManager->register();
        $this->triggerManager->register();
    }
}
