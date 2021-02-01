<?php

declare(strict_types=1);
/**
 * This file is part of hyperf-trigger.
 *
 * @link     https://github.com/friendsofhyperf/trigger
 * @document https://github.com/friendsofhyperf/trigger/blob/main/README.md
 * @contact  huangdijia@gmail.com
 */
namespace FriendsOfHyperf\Trigger\Traits;

use Hyperf\Process\AbstractProcess;

trait Debug
{
    protected function debug(string $message = '', array $context = []): void
    {
        $process = ($this instanceof AbstractProcess) ? $this : $this->process;

        $message = sprintf(
            '[%s] %s by %s. %s',
            $process->name,
            $message,
            get_class($process),
            json_encode($context, JSON_UNESCAPED_UNICODE)
        );

        if ($this->logger ?? null) {
            $this->logger->info($message);
        } else {
            echo $message, "\n";
        }
    }
}
