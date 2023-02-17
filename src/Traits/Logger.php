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
use Psr\Log\LoggerInterface;

/**
 * @property ?string $pool
 * @property ?LoggerInterface $logger
 */
trait Logger
{
    protected function info(string $message, array $context = []): void
    {
        $this->getLogger()->info($this->formatMessage($message, $context));
    }

    protected function debug(string $message, array $context = []): void
    {
        $this->getLogger()->debug($this->formatMessage($message, $context));
    }

    protected function warning(string $message, array $context = []): void
    {
        $this->getLogger()->warning($this->formatMessage($message, $context));
    }

    protected function error(string $message, array $context = []): void
    {
        $this->getLogger()->error($this->formatMessage($message, $context));
    }

    protected function formatMessage(string $message, array $context = []): string
    {
        return sprintf(
            '[trigger%s] %s %s',
            /* @phpstan-ignore-next-line */
            isset($this->pool) ? ".{$this->pool}" : 'default',
            $message,
            $context ? json_encode($context, JSON_UNESCAPED_UNICODE) : ''
        );
    }

    protected function getLogger(): LoggerInterface
    {
        /* @phpstan-ignore-next-line */
        if (isset($this->logger) && $this->logger instanceof LoggerInterface) {
            return $this->logger;
        }

        return ApplicationContext::getContainer()->get(StdoutLoggerInterface::class);
    }
}
