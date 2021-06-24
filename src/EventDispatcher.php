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

use Hyperf\Utils\Coroutine;
use Swoole\Coroutine\Channel;

class EventDispatcher extends \Symfony\Component\EventDispatcher\EventDispatcher
{
    /**
     * @var Channel
     */
    protected $chan;

    public function __construct()
    {
        parent::__construct();

        $this->chan = new Channel(1000);

        Coroutine::create(function () {
            while (true) {
                [$event, $eventName] = $this->chan->pop();
                parent::dispatch($event, $eventName);
            }
        });
    }

    public function dispatch(object $event, ?string $eventName = null): object
    {
        $this->chan->push(func_get_args());

        return $event;
    }
}
