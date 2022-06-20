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

use FriendsOfHyperf\Trigger\Traits\Logger;
use Hyperf\Contract\ConfigInterface;

class ReplicationFactory
{
    use Logger;

    public function __construct(protected ConfigInterface $config)
    {
    }

    public function make(string $replication = ''): Replication
    {
        return make(Replication::class, [
            'replication' => $replication,
            'options' => (array) $this->config->get(sprintf('trigger.%s', $replication), []),
        ]);
    }
}
