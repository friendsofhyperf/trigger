<?php

declare(strict_types=1);
/**
 * This file is part of hyperf-trigger.
 *
 * @link     https://github.com/friendsofhyperf/trigger
 * @document https://github.com/friendsofhyperf/trigger/blob/main/README.md
 * @contact  huangdijia@gmail.com
 */
namespace FriendsOfHyperf\Trigger;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Process\AbstractProcess;
use Hyperf\Process\ProcessManager;
use Psr\Container\ContainerInterface;

class ReplicationManager
{
    public function __construct(
        protected ContainerInterface $container,
        protected ConfigInterface $config
    ) {
    }

    public function run()
    {
        $pools = $this->config->get('trigger', []);

        foreach ($pools as $pool => $options) {
            $replication = make(Replication::class, [
                'pool' => $pool,
                'options' => (array) $options,
            ]);
            $process = $this->createProcess($replication);
            $process->name = $replication->getName();
            $process->nums = 1;
            ProcessManager::register($process);
        }
    }

    protected function createProcess(Replication $replication): AbstractProcess
    {
        return new class($replication) extends AbstractProcess {
            public function __construct(protected Replication $replication)
            {
            }

            public function handle(): void
            {
                $this->replication->start();
            }
        };
    }
}
