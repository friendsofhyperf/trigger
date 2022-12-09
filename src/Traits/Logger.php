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

use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Utils\ApplicationContext;

trait Logger
{
    protected function info(string $message, array $context = []): void
    {
        $this->getLogger()->info($this->messageFormat($message, $context));
    }

    protected function debug(string $message, array $context = []): void
    {
        $this->getLogger()->debug($this->messageFormat($message, $context));
    }

    protected function warning(string $message, array $context = []): void
    {
        $this->getLogger()->warning($this->messageFormat($message, $context));
    }

    protected function messageFormat(string $message, array $context = []): string
    {
        return sprintf(
            '[trigger%s] %s %s',
            /* @phpstan-ignore-next-line */
            isset($this->replication) ? ".{$this->replication}" : '',
            $message,
            $context ? json_encode($context, JSON_UNESCAPED_UNICODE) : ''
        );
    }

    protected function getLogger(): StdoutLoggerInterface
    {
        /* @phpstan-ignore-next-line */
        return isset($this->logger) ? $this->logger : ApplicationContext::getContainer()->get(StdoutLoggerInterface::class);
    }
}
