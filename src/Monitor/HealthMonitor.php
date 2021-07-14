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
use Hyperf\Utils\Coroutine;
use MySQLReplication\BinLog\BinLogCurrent;

class HealthMonitor
{
    use Logger;

    /**
     * @var ConsumeProcess
     */
    private $process;

    /**
     * @var int
     */
    private $monitorInterval;

    /**
     * @var int
     */
    private $snapShortInterval;

    /**
     * @var BinLogCurrentSnapshotInterface
     */
    private $binLogCurrentSnapshot;

    /**
     * @var BinLogCurrent
     */
    private $binLogCurrent;

    public function __construct(ConsumeProcess $process, BinLogCurrentSnapshotInterface $binLogCurrentSnapshot, int $monitorInterval = 10, int $snapShortInterval = 10)
    {
        $this->process = $process;
        $this->monitorInterval = $monitorInterval;
        $this->snapShortInterval = $snapShortInterval;
        $this->binLogCurrentSnapshot = $binLogCurrentSnapshot;
    }

    public function process()
    {
        // Monitor binLogCurrent
        Coroutine::create(function () {
            $this->process->getCoordinator()->yield();

            $this->info('BinLogCurrent monitor booted.');

            while (true) {
                if ($this->process->isStopped()) {
                    $this->warn('Process stopped.');
                    break;
                }

                if ($this->binLogCurrent) {
                    $this->info(
                        sprintf(
                            'Health monitoring, binLogCurrent: %s',
                            json_encode($this->binLogCurrent->jsonSerialize())
                        )
                    );
                }

                sleep($this->monitorInterval ?? 10);
            }
        });

        // Health check and set snapshot
        Coroutine::create(function () {
            $this->process->getCoordinator()->yield();

            $this->info('Health monitor booted.');

            while (true) {
                if ($this->process->isStopped()) {
                    $this->warn('Process stopped.');
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
