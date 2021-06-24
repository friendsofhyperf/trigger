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
use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Process\AbstractProcess;
use Psr\Container\ContainerInterface;

class ConsumeProcess extends AbstractProcess
{
    /**
     * @var ConfigInterface
     */
    protected $config;

    /**
     * @var StdoutLoggerInterface
     */
    protected $logger;

    /**
     * @var string
     */
    protected $replication = 'default';

    /**
     * @var ReplicationFactory
     */
    private $replicationFactory;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->config = $container->get(ConfigInterface::class);
        $this->logger = $container->get(StdoutLoggerInterface::class);
        $this->replicationFactory = $container->get(ReplicationFactory::class);
    }

    public function handle(): void
    {
        $this->replicationFactory->make($this->replication)->consume();
    }
}
