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
use MySQLReplication\BinLog\BinLogCurrent;
use Swoole\Timer;

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

    /**
     * For logger.
     * @var string
     */
    private $replication;

    private $monitorTimerId;

    private $snapShortTimerId;

    public function __construct(ConsumeProcess $process, BinLogCurrentSnapshotInterface $binLogCurrentSnapshot, int $monitorInterval = 10, int $snapShortInterval = 10)
    {
        $this->process = $process;
        $this->replication = $process->getReplication();
        $this->monitorInterval = $monitorInterval;
        $this->snapShortInterval = $snapShortInterval;
        $this->binLogCurrentSnapshot = $binLogCurrentSnapshot;
    }

    public function process()
    {
        // Monitor binLogCurrent
        $this->monitorTimerId = Timer::tick($this->monitorInterval * 1000, function () {
            if ($this->process->isStopped()) {
                $this->warning('Process stopped.');
                $this->monitorTimerId && Timer::clear($this->monitorTimerId);
                return;
            }

            if ($this->binLogCurrent instanceof BinLogCurrent) {
                $this->info(
                    sprintf(
                        'Health monitoring, binLogCurrent: %s',
                        json_encode($this->binLogCurrent->jsonSerialize())
                    )
                );
            }
        });

        // Health check and set snapshot
        $this->snapShortTimerId = Timer::tick($this->snapShortInterval * 1000, function () {
            if ($this->process->isStopped()) {
                $this->warning('Process stopped.');
                $this->snapShortTimerId && Timer::clear($this->snapShortTimerId);
                return;
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
        });
    }

    public function setBinLogCurrent(BinLogCurrent $binLogCurrent): void
    {
        $this->binLogCurrent = $binLogCurrent;
    }
}
