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

use FriendsOfHyperf\Trigger\Annotation\Trigger;
use Hyperf\Command\Annotation\Command;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Di\Annotation\AnnotationCollector;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * @Command
 */
class TriggersCommand extends HyperfCommand
{
    protected $name = 'trigger:triggers';

    public function __construct(ContainerInterface $container)
    {
        parent::__construct();
    }

    public function configure()
    {
        parent::configure();
        $this->addOption('replication', 'R', InputOption::VALUE_OPTIONAL, 'Replication');
        $this->addOption('table', 'T', InputOption::VALUE_OPTIONAL, 'Table');
        $this->setDescription('List all triggers.');
    }

    public function handle()
    {
        $triggers = AnnotationCollector::getClassesByAnnotation(Trigger::class);
        $rows = collect($triggers)
            ->filter(function ($property, $class) {
                if ($this->input->getOption('replication')) {
                    return $this->input->getOption('replication') == $property->replication;
                }
                return true;
            })
            ->filter(function ($property, $class) {
                if ($this->input->getOption('table')) {
                    return $this->input->getOption('table') == $property->table;
                }
                return true;
            })
            ->transform(function ($property, $class) {
                return [$property->replication, $property->table, implode(',', $property->events), $class, $property->priority];
            });

        $this->info('Triggers:');
        $this->table(['Replication', 'Table', 'Events', 'Trigger', 'Priority'], $rows);
    }
}
