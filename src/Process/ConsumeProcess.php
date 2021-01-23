<?php

declare(strict_types=1);
/**
 * This file is part of hyperf-trigger.
 *
 * @link     https://github.com/friendsofhyperf/trigger
 * @document https://github.com/friendsofhyperf/trigger/blob/main/README.md
 * @contact  huangdijia@gmail.com
 */
namespace FriendsOfHyperf\Trigger\Process;

use FriendsOfHyperf\Trigger\ReplicationFactory;
use FriendsOfHyperf\Trigger\Subscriber\HeartbeatSubscriber;
use FriendsOfHyperf\Trigger\Subscriber\TriggerSubscriber;
use FriendsOfHyperf\Trigger\SubscriberProviderFactory;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Process\AbstractProcess;
use Psr\Container\ContainerInterface;

class ConsumeProcess extends AbstractProcess
{
    /**
     * @var string
     */
    protected $replication = 'default';

    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var ReplicationFactory
     */
    protected $replicationFactory;

    /**
     * @var SubscriberProviderFactory
     */
    protected $subscriberProviderFactory;

    /**
     * @var StdoutLoggerInterface
     */
    protected $logger;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->subscriberProviderFactory = $container->get(SubscriberProviderFactory::class);
        $this->replicationFactory = $container->get(ReplicationFactory::class);
        $this->logger = $container->get(StdoutLoggerInterface::class);

        $config = $container->get(ConfigInterface::class)->get('trigger.' . $this->replication);

        $this->name = "trigger.{$this->replication}";
        $this->nums = $config['processes'] ?? 1;
    }

    public function handle(): void
    {
        $replication = $this->replicationFactory->get($this->replication);
        $subscribers = $this->subscriberProviderFactory
            ->get($this->replication)
            ->getSubscribers();
        $subscribers[] = make(TriggerSubscriber::class, ['replication' => $this->replication]);
        $subscribers[] = make(HeartbeatSubscriber::class, ['replication' => $this->replication]);

        foreach ($subscribers as $subscriber) {
            $replication->registerSubscriber($subscriber);
            $this->logger->debug(sprintf('[trigger.%s] %s registered by %s process.', $this->replication, get_class($subscriber), get_class($this)));
        }

        $replication->run();
    }
}
