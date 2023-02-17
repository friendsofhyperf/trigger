<?php

declare(strict_types=1);
/**
 * This file is part of hyperf-trigger.
 *
 * @link     https://github.com/friendsofhyperf/trigger
 * @document https://github.com/friendsofhyperf/trigger/blob/main/README.md
 * @contact  huangdijia@gmail.com
 */
namespace FriendsOfHyperf\Trigger\Monitor;

use FriendsOfHyperf\Trigger\Event\OnReplicationStop;
use FriendsOfHyperf\Trigger\Replication;
use FriendsOfHyperf\Trigger\Snapshot\BinLogCurrentSnapshotInterface;
use FriendsOfHyperf\Trigger\Traits\Logger;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Coordinator\CoordinatorManager;
use Hyperf\Coordinator\Timer;
use Hyperf\Utils\Coroutine;
use MySQLReplication\BinLog\BinLogCurrent;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

class HealthMonitor
{
    use Logger;

    protected BinLogCurrent $binLogCurrent;

    protected int $monitorInterval = 10;

    protected int $snapShortInterval = 10;

    protected string $pool;

    protected BinLogCurrentSnapshotInterface $binLogCurrentSnapshot;

    protected Timer $timer;

    protected StdoutLoggerInterface $logger;

    public function __construct(protected ContainerInterface $container, protected Replication $replication)
    {
        $this->pool = $replication->getPool();
        $this->monitorInterval = (int) $replication->getOption('health_monitor.interval', 10);
        $this->snapShortInterval = (int) $replication->getOption('snapshot.interval', 10);
        $this->binLogCurrentSnapshot = $replication->getBinLogCurrentSnapshot();
        $this->logger = $this->container->get(StdoutLoggerInterface::class);
        $this->timer = new Timer($this->logger);
    }

    public function process(): void
    {
        Coroutine::create(function () {
            CoordinatorManager::until($this->replication->getIdentifier())->yield();

            // Monitor binLogCurrent
            $this->timer->tick($this->monitorInterval, function () {
                if ($this->binLogCurrent instanceof BinLogCurrent) {
                    $this->debug(
                        sprintf(
                            'Health monitoring, binLogCurrent: %s',
                            json_encode($this->binLogCurrent->jsonSerialize(), JSON_THROW_ON_ERROR)
                        )
                    );
                }
            });

            // Health check and set snapshot
            $this->timer->tick($this->snapShortInterval, function () {
                if (! $this->binLogCurrent instanceof BinLogCurrent) {
                    return;
                }

                if (
                    $this->binLogCurrentSnapshot->get() instanceof BinLogCurrent
                    && $this->binLogCurrentSnapshot->get()->getBinLogPosition() == $this->binLogCurrent->getBinLogPosition()
                ) {
                    $this->container->get(EventDispatcherInterface::class)?->dispatch(new OnReplicationStop($this->pool, $this->binLogCurrent));
                }

                $this->binLogCurrentSnapshot->set($this->binLogCurrent);
            });
        });
    }

    public function setBinLogCurrent(BinLogCurrent $binLogCurrent): void
    {
        $this->binLogCurrent = $binLogCurrent;
    }
}
