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

use FriendsOfHyperf\Trigger\Process\ConsumeProcess;
use FriendsOfHyperf\Trigger\Snapshot\BinLogCurrentSnapshotInterface;
use FriendsOfHyperf\Trigger\Traits\Logger;
use Hyperf\Coordinator\CoordinatorManager;
use Hyperf\Utils\Coroutine;
use MySQLReplication\BinLog\BinLogCurrent;

class HealthMonitor
{
    use Logger;

    private \MySQLReplication\BinLog\BinLogCurrent $binLogCurrent;

    /**
     * For logger.
     */
    private string $replication;

    private int $monitorInterval = 10;

    private int $snapShortInterval = 10;

    private BinLogCurrentSnapshotInterface $binLogCurrentSnapshot;

    public function __construct(private ConsumeProcess $process)
    {
        $this->replication = $process->getReplication();
        $this->monitorInterval = (int) $process->getOption('health_monitor.interval', 10);
        $this->snapShortInterval = (int) $process->getOption('snapshot.interval', 10);
        $this->binLogCurrentSnapshot = $process->getBinLogCurrentSnapshot();
    }

    public function process(): void
    {
        // Monitor binLogCurrent
        Coroutine::create(function () {
            CoordinatorManager::until($this->process::class)->yield();

            while (true) {
                if ($this->process->isStopped()) {
                    $this->warning('Process stopped.');
                    break;
                }

                if ($this->binLogCurrent instanceof BinLogCurrent) {
                    $this->info(
                        sprintf(
                            'Health monitoring, binLogCurrent: %s',
                            json_encode($this->binLogCurrent->jsonSerialize(), JSON_THROW_ON_ERROR)
                        )
                    );
                }

                sleep($this->monitorInterval);
            }
        });

        // Health check and set snapshot
        Coroutine::create(function () {
            CoordinatorManager::until($this->process::class)->yield();

            while (true) {
                if ($this->process->isStopped()) {
                    $this->warning('Process stopped.');
                    break;
                }

                if ($this->binLogCurrent instanceof BinLogCurrent) {
                    if (
                    $this->binLogCurrentSnapshot->get() instanceof BinLogCurrent
                    && $this->binLogCurrentSnapshot->get()->getBinLogPosition() == $this->binLogCurrent->getBinLogPosition()
                ) {
                        $this->process->callOnReplicationStopped($this->binLogCurrent);
                    }

                    $this->binLogCurrentSnapshot->set($this->binLogCurrent);
                }

                sleep($this->snapShortInterval);
            }
        });
    }

    public function setBinLogCurrent(BinLogCurrent $binLogCurrent): void
    {
        $this->binLogCurrent = $binLogCurrent;
    }
}
