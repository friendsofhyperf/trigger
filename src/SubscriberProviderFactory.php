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

use FriendsOfHyperf\Trigger\Annotation\Subscriber;
use FriendsOfHyperf\Trigger\Subscriber\AbstractSubscriber;
use Hyperf\Di\Annotation\AnnotationCollector;

class SubscriberProviderFactory
{
    /**
     * @var SubscriberProvider[]
     */
    protected $managers = [];

    /**
     * @return SubscriberProvider
     */
    public function get(string $replication = 'default')
    {
        if (! isset($this->managers[$replication])) {
            $provider = new SubscriberProvider();
            $this->registerAnnotations($provider, $replication);
            $this->managers[$replication] = $provider;
        }

        return $this->managers[$replication];
    }

    public function registerAnnotations(SubscriberProvider $provider, string $replication): void
    {
        $classes = AnnotationCollector::getClassesByAnnotation(Subscriber::class);

        foreach ($classes as $class => $property) {
            $subscriber = make($class, ['replication' => $replication]);

            if (! ($subscriber instanceof AbstractSubscriber)) {
                continue;
            }

            $provider->register($subscriber, $property->priority ?? 1);
        }
    }
}
