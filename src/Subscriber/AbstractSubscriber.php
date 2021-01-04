<?php

declare(strict_types=1);
/**
 * This file is part of hyperf-trigger.
 *
 * @link     https://github.com/friendsofhyperf/trigger
 * @document https://github.com/friendsofhyperf/trigger/blob/main/README.md
 * @contact  huangdijia@gmail.com
 */
namespace FriendsOfHyperf\Trigger\Subscriber;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use MySQLReplication\Event\EventSubscribers;
use Psr\Container\ContainerInterface;

abstract class AbstractSubscriber extends EventSubscribers
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var array
     */
    protected $config;

    /**
     * @var string
     */
    protected $replication;

    /**
     * @var StdoutLoggerInterface
     */
    protected $logger;

    public function __construct(ContainerInterface $container, string $replication = 'default')
    {
        $this->container = $container;
        $this->replication = $replication;
        $this->config = $container->get(ConfigInterface::class)->get('trigger.' . $replication) ?? [];
        $this->logger = $container->get(StdoutLoggerInterface::class);
    }
}
