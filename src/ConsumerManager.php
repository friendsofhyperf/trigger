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

class ConsumerManager
{
    public function __construct(
        protected ContainerInterface $container,
        protected ConfigInterface $config
    ) {
    }

    public function run()
    {
        $connections = $this->config->get('trigger.connections', []);

        foreach ($connections as $connection => $options) {
            $consumer = make(Consumer::class, [
                'connection' => $connection,
                'options' => (array) $options,
            ]);
            $process = $this->createProcess($consumer);
            $process->name = $consumer->getName();
            $process->nums = 1;
            ProcessManager::register($process);
        }
    }

    protected function createProcess(Consumer $consumer): AbstractProcess
    {
        return new class($consumer) extends AbstractProcess {
            public function __construct(protected Consumer $consumer)
            {
            }

            public function handle(): void
            {
                $this->consumer->start();
            }
        };
    }
}
