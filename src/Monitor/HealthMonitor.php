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

use FriendsOfHyperf\Trigger\Replication;
use FriendsOfHyperf\Trigger\Snapshot\BinLogCurrentSnapshotInterface;
use FriendsOfHyperf\Trigger\Traits\Logger;
use Hyperf\Coordinator\Constants;
use Hyperf\Coordinator\CoordinatorManager;
use Hyperf\Utils\Coroutine;
use MySQLReplication\BinLog\BinLogCurrent;

class HealthMonitor
{
    use Logger;

    private \MySQLReplication\BinLog\BinLogCurrent $binLogCurrent;

    private int $monitorInterval = 10;

    private int $snapShortInterval = 10;

    private string $replication;

    private BinLogCurrentSnapshotInterface $binLogCurrentSnapshot;

    public function __construct(private Replication $r)
    {
        $this->replication = $r->getReplication();
        $this->monitorInterval = (int) $r->getOption('health_monitor.interval', 10);
        $this->snapShortInterval = (int) $r->getOption('snapshot.interval', 10);
        $this->binLogCurrentSnapshot = $r->getBinLogCurrentSnapshot();
    }

    public function process(): void
    {
        // Monitor binLogCurrent
        Coroutine::create(function () {
            CoordinatorManager::until($this->r->getIdentifier())->yield();

            while (true) {
                $isExited = CoordinatorManager::until(Constants::WORKER_EXIT)->yield($this->monitorInterval);

                if ($isExited) {
                    $this->warning('Process stopped.');
                    break;
                }

                if ($this->binLogCurrent instanceof BinLogCurrent) {
                    $this->debug(
                        sprintf(
                            'Health monitoring, binLogCurrent: %s',
                            json_encode($this->binLogCurrent->jsonSerialize(), JSON_THROW_ON_ERROR)
                        )
                    );
                }
            }
        });

        // Health check and set snapshot
        Coroutine::create(function () {
            CoordinatorManager::until($this->r->getIdentifier())->yield();

            while (true) {
                $isExited = CoordinatorManager::until(Constants::WORKER_EXIT)->yield($this->snapShortInterval);

                if ($isExited) {
                    $this->warning('Process stopped.');
                    break;
                }

                if ($this->binLogCurrent instanceof BinLogCurrent) {
                    if (
                    $this->binLogCurrentSnapshot->get() instanceof BinLogCurrent
                    && $this->binLogCurrentSnapshot->get()->getBinLogPosition() == $this->binLogCurrent->getBinLogPosition()
                ) {
                        $this->r->callOnReplicationStopped($this->binLogCurrent);
                    }

                    $this->binLogCurrentSnapshot->set($this->binLogCurrent);
                }
            }
        });
    }

    public function setBinLogCurrent(BinLogCurrent $binLogCurrent): void
    {
        $this->binLogCurrent = $binLogCurrent;
    }
}
