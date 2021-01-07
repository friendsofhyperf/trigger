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
use Hyperf\Contract\ConfigInterface;
use Psr\Container\ContainerInterface;
use RuntimeException;

class ReplicationFactory implements FactoryInterface
{
    /**
     * @var ConfigInterface
     */
    protected $config;

    /**
     * @var Replication[]
     */
    protected $replications = [];

    /**
     * @var PositionFactory
     */
    protected $positionFactory;

    public function __construct(ContainerInterface $container)
    {
        $this->config = $container->get(ConfigInterface::class);
        $this->positionFactory = $container->get(PositionFactory::class);
    }

    /**
     * @throws RuntimeException
     * @return Replication
     */
    public function get(string $replication = 'default')
    {
        if (! isset($this->replications[$replication])) {
            $key = 'trigger.' . $replication;

            if (! $this->config->has($key)) {
                throw new RuntimeException('config ' . $key . ' is undefined.');
            }

            $config = $this->config->get($key);

            if ($binLogCurrent = $this->positionFactory->get($replication)->get()) {
                $config['binlog_filename'] = $binLogCurrent->getBinFileName();
                $config['binlog_position'] = $binLogCurrent->getBinLogPosition();
            }

            $this->replications[$replication] = make(Replication::class, ['config' => $config]);
        }

        return $this->replications[$replication];
    }
}
