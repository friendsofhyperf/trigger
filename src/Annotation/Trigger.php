<?php

declare(strict_types=1);
/**
 * This file is part of hyperf-trigger.
 *
 * @link     https://github.com/friendsofhyperf/trigger
 * @document https://github.com/friendsofhyperf/trigger/blob/main/README.md
 * @contact  huangdijia@gmail.com
 */
namespace FriendsOfHyperf\Trigger\Annotation;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Di\Annotation\AbstractAnnotation;
use Hyperf\Utils\ApplicationContext;

/**
 * @Annotation
 * @Target("CLASS")
 */
class Trigger extends AbstractAnnotation
{
    public string $replication = 'default';

    public string $database;

    public string $table;

    public array $events = [];

    public int $priority = 0;

    public function __construct($value = null)
    {
        if (isset($value['on'])) {
            $events = $value['on'];

            if ($events == '*') {
                $events = ['write', 'update', 'delete'];
            }

            if (is_string($events) && stripos($events, ',')) {
                $events = explode(',', $events);
                $events = array_map(function ($item) {
                    return trim($item);
                }, $events);
            }

            $value['events'] = (array) $events;
        }

        if (! isset($value['database'])) {
            /** @var ConfigInterface */
            $config = ApplicationContext::getContainer()->get(ConfigInterface::class);
            $key = sprintf('trigger.%s.databases_only');
            $value['database'] = $config->get($key)[0] ?? '';
        }

        parent::__construct($value);
    }
}
