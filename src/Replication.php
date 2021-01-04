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

use MySQLReplication\BinaryDataReader\BinaryDataReaderException;
use MySQLReplication\BinLog\BinLogException;
use MySQLReplication\Config\ConfigBuilder;
use MySQLReplication\Exception\MySQLReplicationException;
use MySQLReplication\JsonBinaryDecoder\JsonBinaryDecoderException;
use MySQLReplication\MySQLReplicationFactory;
use MySQLReplication\Socket\SocketException;
use RuntimeException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class Replication
{
    /**
     * @var MySQLReplicationFactory
     */
    protected $binLogStream;

    public function __construct(array $config = [])
    {
        $configBuilder = new ConfigBuilder();
        $configBuilder->withUser($config['user'] ?? 'root')
            ->withHost($config['host'] ?? '127.0.0.1')
            ->withPassword($config['password'] ?? 'root')
            ->withPort((int) $config['port'] ?? 3306)
            ->withSlaveId($this->getSlaveId())
            ->withHeartbeatPeriod((float) $config['heartbeat_period'] ?? 3)
            ->withDatabasesOnly((array) $config['databases_only'] ?? [])
            ->withtablesOnly((array) $config['tables_only'] ?? []);

        if ($config['binlog_filename']) {
            $configBuilder->withBinLogFileName($config['binlog_filename']);
        }

        if ($config['binlog_position']) {
            $configBuilder->withBinLogPosition((int) $config['binlog_position']);
        }

        $this->binLogStream = new MySQLReplicationFactory($configBuilder->build());
    }

    public function registerSubscriber(EventSubscriberInterface $eventSubscriber)
    {
        $this->binLogStream->registerSubscriber($eventSubscriber);
    }

    /**
     * @throws SocketException
     * @throws JsonBinaryDecoderException
     * @throws BinaryDataReaderException
     * @throws BinLogException
     * @throws MySQLReplicationException
     */
    public function run()
    {
        $this->binLogStream->run();
    }

    /**
     * @throws RuntimeException
     */
    protected function getInternalIp(): string
    {
        $ips = swoole_get_local_ip();

        if (is_array($ips) && ! empty($ips)) {
            return current($ips);
        }

        /** @var mixed|string $ip */
        $ip = gethostbyname(gethostname());
        if (is_string($ip)) {
            return $ip;
        }

        throw new RuntimeException('Can not get the internal IP.');
    }

    /**
     * @throws RuntimeException
     * @return int
     */
    protected function getSlaveId()
    {
        return (int) ip2long($this->getInternalIp());
    }
}
