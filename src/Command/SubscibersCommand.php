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
use Hyperf\Command\Annotation\Command;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Di\Annotation\AnnotationCollector;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * @Command
 */
class SubscibersCommand extends HyperfCommand
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    protected $name = 'trigger:subscibers';

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
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
            });

        $this->info('Subscibers:');
        $this->table(['Replication', 'Subsciber', 'Priority'], $rows);
    }
}
