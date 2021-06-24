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

use Swoole\Coroutine\Channel;

class ChannelManager
{
    /**
     * @var Channel[]
     */
    private $channels = [];

    public function get(string $replication = 'default'): Channel
    {
        if (! isset($this->channels[$replication])) {
            $this->channels[$replication] = new Channel(100);
        }

        return $this->channels[$replication];
    }
}
