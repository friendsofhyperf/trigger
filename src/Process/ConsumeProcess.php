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
use FriendsOfHyperf\Trigger\SubscriberManagerFactory;
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
     * @var SubscriberManagerFactory
     */
    protected $subscriberManagerFactory;

    /**
     * @var StdoutLoggerInterface
     */
    protected $logger;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->subscriberManagerFactory = $container->get(SubscriberManagerFactory::class);
        $this->replicationFactory = $container->get(ReplicationFactory::class);
        $this->logger = $container->get(StdoutLoggerInterface::class);

        $config = $container->get(ConfigInterface::class)->get('trigger.' . $this->replication);

        $this->name = "trigger.{$this->replication}";
        $this->nums = $config['processes'] ?? 1;
    }

    public function handle(): void
    {
        $replication = $this->replicationFactory->get($this->replication);
        $subscribers = $this->subscriberManagerFactory->get($this->replication)->get();
        $subscribers = array_merge($subscribers, [
            TriggerSubscriber::class,
            HeartbeatSubscriber::class,
        ]);

        foreach ($subscribers as $class) {
            $replication->registerSubscriber(make($class, ['replication' => $this->replication]));
            $this->logger->info(sprintf('[trigger.%s] %s registered by %s process.', $this->replication, $class, __CLASS__));
        }

        $replication->run();
    }
}
