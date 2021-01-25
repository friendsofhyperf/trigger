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
use Hyperf\Utils\ApplicationContext;
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

    public function __construct(ContainerInterface $container)
    {
        $this->logger = $container->get(StdoutLoggerInterface::class);
    }

    /**
     * @return TriggerProvider
     */
    public function get(string $replication = 'default')
    {
        if (! isset($this->providers[$replication])) {
            $this->providers[$replication] = tap(new TriggerProvider(), function ($provider) use ($replication) {
                $this->registerAnnotations($provider, $replication, ApplicationContext::getContainer());
            });
        }

        return $this->providers[$replication];
    }

    private function registerAnnotations(TriggerProvider $provider, string $replication, ContainerInterface $container): void
    {
        $classes = AnnotationCollector::getClassesByAnnotation(Trigger::class);

        foreach ($classes as $class => $property) {
            if ($property->replication != $replication) {
                continue;
            }

            $instance = $container->get($class);

            if (! ($instance instanceof AbstractTrigger)) {
                continue;
            }

            $table = $property->table;

            foreach ($property->events ?? [] as $event) {
                $method = 'on' . ucfirst(strtolower($event)); // onWrite/onUpdate/onDelete
                $provider->on($table, $event, [$instance, $method], $property->priority ?? 1);
            }

            $this->logger->info(sprintf('[trigger.%s] %s [%s] registered by %s.', $this->replication, $class, implode(',', $property->events), get_class($this)));
        }
    }
}
