<?php

declare(strict_types=1);
/**
 * This file is part of hyperf-trigger.
 *
 * @link     https://github.com/friendsofhyperf/trigger
 * @document https://github.com/friendsofhyperf/trigger/blob/main/README.md
 * @contact  huangdijia@gmail.com
 */
namespace FriendsOfHyperf\Trigger\Command;

use FriendsOfHyperf\Trigger\Annotation\Subscriber;
use FriendsOfHyperf\Trigger\Subscriber\SnapshotSubscriber;
use FriendsOfHyperf\Trigger\Subscriber\TriggerSubscriber;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Di\Annotation\AnnotationCollector;
use Psr\Container\ContainerInterface;

class SubscribersCommand extends HyperfCommand
{
    protected ?string $signature = 'describe:subscribers {--P|poll= : Pool}';

    protected string $description = 'List all subscribers.';

    public function __construct(ContainerInterface $container)
    {
        parent::__construct();
    }

    public function handle()
    {
        $subscribers = AnnotationCollector::getClassesByAnnotation(Subscriber::class);
        $rows = collect($subscribers)
            ->filter(function ($property, $class) {
                if ($this->input->getOption('pool')) {
                    return $this->input->getOption('pool') == $property->pool;
                }
                return true;
            })
            ->transform(fn ($property, $class) => [$property->pool, $class, $property->priority])
            ->merge([
                ['[default]', SnapshotSubscriber::class, 1],
                ['[default]', TriggerSubscriber::class, 1],
            ]);

        $this->info('Subscribers:');
        $this->table(['Pool', 'Subscriber', 'Priority'], $rows);
    }
}
