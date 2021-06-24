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

/**
 * @Command
 */
class TriggersCommand extends HyperfCommand
{
    /**
     * @var string
     */
    protected $signature = 'describe:triggers {--replication|R= : Replication} {--table|T= : Table}';

    /**
     * @var string
     */
    protected $description = 'List all triggers.';

    public function __construct(ContainerInterface $container)
    {
        parent::__construct();
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription($this->description);
    }

    public function handle()
    {
        $triggers = AnnotationCollector::getClassesByAnnotation(Trigger::class);

        $rows = collect($triggers)
            ->each(function ($property, $class) {
                /* @var Trigger $property */
                $property->table = $property->table ?? class_basename($class);
            })
            ->filter(function ($property, $class) {
                /* @var Trigger $property */
                if ($this->input->getOption('replication')) {
                    return $this->input->getOption('replication') == $property->replication;
                }
                return true;
            })
            ->filter(function ($property, $class) {
                /* @var Trigger $property */
                if ($this->input->getOption('table')) {
                    return $this->input->getOption('table') == $property->table;
                }
                return true;
            })
            ->transform(function ($property, $class) {
                /* @var Trigger $property */
                return [$property->replication, $property->database, $property->table, implode(',', $property->events), $class, $property->priority];
            });

        $this->info('Triggers:');
        $this->table(['Replication', 'Database', 'Table', 'Events', 'Trigger', 'Priority'], $rows);
    }
}
