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

use FriendsOfHyperf\Trigger\PositionFactory;
use FriendsOfHyperf\Trigger\Process\ConsumeProcess;
use FriendsOfHyperf\Trigger\Traits\Debug;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Utils\Coroutine;
use MySQLReplication\BinLog\BinLogCurrent;
use Psr\Container\ContainerInterface;

class ReplicationMonitor implements MonitorInterface
{
    use Debug;

    /**
     * @var ConsumeProcess
     */
    protected $process;

    /**
     * @var PositionFactory
     */
    protected $positionFactory;

    /**
     * @var StdoutLoggerInterface
     */
    protected $logger;

    public function __construct(ContainerInterface $container, ConsumeProcess $process)
    {
        $this->process = $process;
        $this->positionFactory = $container->get(PositionFactory::class);
        $this->logger = $container->get(StdoutLoggerInterface::class);
    }

    public function run(callable $onReplicationStopped)
    {
        if (! $this->process->isMonitor()) {
            return;
        }

        Coroutine::create(function () use ($onReplicationStopped) {
            $interval = $this->process->getMonitorInterval();

            sleep($interval);

            /** @var null|BinLogCurrent $binLogCache */
            $binLogCache = null;
            $position = $this->positionFactory->get($this->process->getReplication());

            $this->debug('Monitor start');

            while (true) {
                if ($this->process->isStopped()) {
                    $this->debug('Monitor stopped');
                    break;
                }

                $this->debug('Monitor executing');

                $binLogCurrent = $position->get();

                if (! ($binLogCurrent instanceof BinLogCurrent)) {
                    $this->debug('Replication not run yet');
                    sleep($interval);
                    continue;
                }

                if (! ($binLogCache instanceof BinLogCurrent)) {
                    $binLogCache = $binLogCurrent;
                    sleep($interval);
                    continue;
                }

                if ($binLogCurrent->getBinLogPosition() == $binLogCache->getBinLogPosition()) {
                    $onReplicationStopped($binLogCurrent);
                }

                $binLogCache = $binLogCurrent;

                $this->debug(sprintf('Monitor executed, binLogCurrent: %s', json_encode($binLogCurrent->jsonSerialize())));

                sleep($interval);
            }
        });
    }
}
