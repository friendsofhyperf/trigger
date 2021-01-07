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
use FriendsOfHyperf\Trigger\Subscriber\HeartbeatSubscriber;
use FriendsOfHyperf\Trigger\Subscriber\TriggerSubscriber;
use Hyperf\Command\Annotation\Command;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Di\Annotation\AnnotationCollector;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * @Command
 */
class SubscribersCommand extends HyperfCommand
{
    protected $name = 'trigger:subscribers';

    public function __construct(ContainerInterface $container)
    {
        parent::__construct();
    }

    public function configure()
    {
        parent::configure();
        $this->addOption('replication', 'R', InputOption::VALUE_OPTIONAL, 'replication');
        $this->setDescription('List all subscribers.');
    }

    public function handle()
    {
        $subscribers = AnnotationCollector::getClassesByAnnotation(Subscriber::class);
        $rows = collect($subscribers)
            ->filter(function ($property, $class) {
                if ($this->input->getOption('replication')) {
                    return $this->input->getOption('replication') == $property->replication;
                }
                return true;
            })
            ->transform(function ($property, $class) {
                return [$property->replication, $class, $property->priority];
            })
            ->merge([
                ['[default]', TriggerSubscriber::class, 1],
                ['[default]', HeartbeatSubscriber::class, 1],
            ]);

        $this->info('Subscibers:');
        $this->table(['Replication', 'Subsciber', 'Priority'], $rows);
    }
}
