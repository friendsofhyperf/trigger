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

use FriendsOfHyperf\Trigger\Constact\FactoryInterface;
use Psr\Container\ContainerInterface;

class PositionFactory implements FactoryInterface
{
    /**
     * @var array
     */
    protected $positions = [];

    /**
     * @var ContainerInterface
     */
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @return Position
     */
    public function get(string $replication = 'default')
    {
        if (! isset($this->positions[$replication])) {
            $this->positions[$replication] = make(Position::class, ['replication' => $replication]);
        }

        return $this->positions[$replication];
    }
}
