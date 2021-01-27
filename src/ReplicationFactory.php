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

use FriendsOfHyperf\Trigger\Exception\ConfigNotFoundException;
use Hyperf\Contract\ConfigInterface;
use MySQLReplication\Config\ConfigBuilder;
use MySQLReplication\MySQLReplicationFactory;
use Psr\Container\ContainerInterface;
use RuntimeException;

class ReplicationFactory
{
    /**
     * @var ConfigInterface
     */
    protected $config;

    /**
     * @var MySQLReplicationFactory[]
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
     * @throws ConfigNotFoundException
     * @return MySQLReplicationFactory
     */
    public function get(string $replication = 'default')
    {
        if (! isset($this->replications[$replication])) {
            $key = 'trigger.' . $replication;

            if (! $this->config->has($key)) {
                throw new ConfigNotFoundException('config ' . $key . ' is not found.');
            }

            $config = $this->config->get($key);

            if ($binLogCurrent = $this->positionFactory->get($replication)->get()) {
                $config['binlog_filename'] = $binLogCurrent->getBinFileName();
                $config['binlog_position'] = $binLogCurrent->getBinLogPosition();
            }

            $this->replications[$replication] = $this->make($config);
        }

        return $this->replications[$replication];
    }

    /**
     * @throws RuntimeException
     */
    public function make(array $config = []): MySQLReplicationFactory
    {
        $configBuilder = new ConfigBuilder();
        $configBuilder->withUser($config['user'] ?? 'root')
            ->withHost($config['host'] ?? '127.0.0.1')
            ->withPassword($config['password'] ?? 'root')
            ->withPort((int) $config['port'] ?? 3306)
            ->withSlaveId($this->generateSlaveId())
            ->withHeartbeatPeriod((float) $config['heartbeat_period'] ?? 3)
            ->withDatabasesOnly((array) $config['databases_only'] ?? [])
            ->withtablesOnly((array) $config['tables_only'] ?? []);

        if (isset($config['binlog_filename'])) {
            $configBuilder->withBinLogFileName($config['binlog_filename']);
        }

        if (isset($config['binlog_position'])) {
            $configBuilder->withBinLogPosition((int) $config['binlog_position']);
        }

        return new MySQLReplicationFactory($configBuilder->build());
    }

    /**
     * @throws RuntimeException
     */
    protected function generateSlaveId(): int
    {
        return (int) ip2long(Util::getInternalIp()) + rand(0, 999);
    }
}
