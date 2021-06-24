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

use FriendsOfHyperf\Trigger\Mutex\ServerMutexInterface;
use FriendsOfHyperf\Trigger\ReplicationFactory;
use FriendsOfHyperf\Trigger\Util;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Process\AbstractProcess;
use Psr\Container\ContainerInterface;

class ConsumeProcess extends AbstractProcess
{
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
    protected $replicationFactory;

    /**
     * @var bool
     */
    protected $onOneServer = false;

    /**
     * @var null|ServerMutexInterface
     */
    protected $mutex;

    /**
     * @var int
     */
    protected $mutexExpires = 30;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->name = 'trigger.' . $this->replication;
        $this->logger = $container->get(StdoutLoggerInterface::class);
        $this->replicationFactory = $container->get(ReplicationFactory::class);

        if ($this->onOneServer) {
            $this->mutex = make(ServerMutexInterface::class, [
                'name' => (string) $this->name,
                'seconds' => (int) $this->mutexExpires,
                'owner' => (string) Util::getInternalIp(),
            ]);
        }
    }

    public function handle(): void
    {
        $callback = function () {
            $this->replicationFactory
                ->make($this->replication)
                ->run();
        };

        if ($this->mutex) {
            $this->mutex->attempt($callback);
        } else {
            $callback();
        }
    }
}
