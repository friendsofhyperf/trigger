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
    public function debug(string $message, array $context = []): void
    {
        /** @var StdoutLoggerInterface */
        $logger = $this->logger ?? ApplicationContext::getContainer()->get(StdoutLoggerInterface::class);
        $logger->info(sprintf(
            '[trigger%s] %s %s',
            isset($this->replication) ? ".{$this->replication}" : '',
            $message,
            $context ? json_encode($context, JSON_UNESCAPED_UNICODE) : ''
        ));
    }
}
