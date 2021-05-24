<?php

declare(strict_types=1);
/**
 * This file is part of hyperf-trigger.
 *
 * @link     https://github.com/friendsofhyperf/trigger
 * @document https://github.com/friendsofhyperf/trigger/blob/main/README.md
 * @contact  huangdijia@gmail.com
 */
namespace FriendsOfHyperf\Trigger\Subscriber;

use FriendsOfHyperf\Trigger\Position;
use FriendsOfHyperf\Trigger\PositionFactory;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Utils\Coroutine;
use MySQLReplication\BinLog\BinLogCurrent;
use MySQLReplication\Event\DTO\EventDTO;

class HeartbeatSubscriber extends AbstractSubscriber
{
    /**
     * @var Position
     */
    protected $position;

    /**
     * @var null|BinLogCurrent
     */
    protected $binLogCurrent;

    /**
     * @var StdoutLoggerInterface
     */
    protected $logger;

    /**
     * @var int
     */
    protected $interval = 1;

    public function __construct(PositionFactory $factory, StdoutLoggerInterface $logger, string $replication = 'default')
    {
        $this->position = $factory->get($replication);
        $this->logger = $logger;

        Coroutine::create(function () use ($replication) {
            $binLogPosition = 0;

            while (true) {
                if ($this->binLogCurrent instanceof BinLogCurrent && $binLogPosition != $this->binLogCurrent->getBinLogPosition()) {
                    $this->position->set($this->binLogCurrent);
                    $this->logger->info(sprintf('[trigger.%s] BinLogCurrent: %s by %s', $replication, json_encode($this->binLogCurrent->jsonSerialize()), __CLASS__));
                    $binLogPosition = $this->binLogCurrent->getBinLogPosition();
                }

                sleep($this->interval);
            }
        });
    }

    protected function allEvents(EventDTO $event): void
    {
        $this->binLogCurrent = $event->getEventInfo()->getBinLogCurrent();
    }
}
