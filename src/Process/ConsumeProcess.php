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
use FriendsOfHyperf\Trigger\Traits\Logger;
use Hyperf\Process\AbstractProcess;
use Psr\Container\ContainerInterface;

class ConsumeProcess extends AbstractProcess
{
    use Logger;

    public int $nums = 1;

    protected string $replication = 'default';

    public function __construct(
        ContainerInterface $container,
        protected ReplicationFactory $replicationFactory
    ) {
        parent::__construct($container);
    }

    public function handle(): void
    {
        $this->replicationFactory->make($this->replication)->start();
    }
}
