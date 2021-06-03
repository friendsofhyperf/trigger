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

use FriendsOfHyperf\Trigger\Annotation\Trigger;
use FriendsOfHyperf\Trigger\Trigger\AbstractTrigger;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Di\Annotation\AnnotationCollector;
use Psr\Container\ContainerInterface;

class TriggerProviderFactory
{
    /**
     * @var TriggerProvider[]
     */
    protected $providers = [];

    /**
     * @var StdoutLoggerInterface
     */
    protected $logger;

    /**
     * @var ContainerInterface
     */
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->logger = $container->get(StdoutLoggerInterface::class);
    }

    /**
     * @return TriggerProvider
     */
    public function get(string $replication = 'default')
    {
        if (! isset($this->providers[$replication])) {
            $this->providers[$replication] = tap(make(TriggerProvider::class), function ($provider) use ($replication) {
                $this->registerAnnotations($provider, $replication);
            });
        }

        return $this->providers[$replication];
    }

    private function registerAnnotations(TriggerProvider $provider, string $replication): void
    {
        $classes = AnnotationCollector::getClassesByAnnotation(Trigger::class);

        foreach ($classes as $class => $property) {
            /** @var Trigger $property */
            if ($property->replication != $replication) {
                continue;
            }

            /** @var AbstractTrigger $instance */
            $instance = $this->container->get($class);

            if (! ($instance instanceof AbstractTrigger)) {
                $this->logger->warning(sprintf('%s doesn\'t instanceof %s.', $class, AbstractTrigger::class));
                continue;
            }

            $table = $property->table ?? class_basename($class);

            foreach ($property->events ?? [] as $event) {
                $method = 'on' . ucfirst(strtolower($event)); // onWrite/onUpdate/onDelete
                $provider->on($table, $event, [$instance, $method], $property->priority ?? 1);
            }

            $this->logger->info(sprintf('[trigger.%s] %s [%s] registered by %s.', $replication, $class, implode(',', $property->events), get_class($this)));
        }
    }
}
