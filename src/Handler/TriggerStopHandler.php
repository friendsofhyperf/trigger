<?php

declare(strict_types=1);
/**
 * This file is part of hyperf-trigger.
 *
 * @link     https://github.com/friendsofhyperf/trigger
 * @document https://github.com/friendsofhyperf/trigger/blob/main/README.md
 * @contact  huangdijia@gmail.com
 */
namespace FriendsOfHyperf\Trigger\Handler;

use FriendsOfHyperf\Trigger\Process\ConsumeProcess;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Di\Annotation\AnnotationCollector;
use Hyperf\Process\Annotation\Process;
use Hyperf\Signal\SignalHandlerInterface;
use Psr\Container\ContainerInterface;

class TriggerStopHandler implements SignalHandlerInterface
{
    public function __construct(protected ContainerInterface $container, protected ConfigInterface $config)
    {
    }

    public function listen(): array
    {
        return [
            [self::WORKER, SIGTERM],
            [self::WORKER, SIGINT],
        ];
    }

    public function handle(int $signal): void
    {
        if ($signal !== SIGINT) {
            $time = $this->config->get('server.settings.max_wait_time', 3);
            sleep($time);
        }

        $annotations = AnnotationCollector::getClassesByAnnotation(Process::class);

        foreach ($annotations as $class => $property) {
            $process = $this->container->get($class);
            if ($process instanceof ConsumeProcess) {
                // todo
            }
        }
    }
}
