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

use Attribute;
use Hyperf\Di\Annotation\AbstractAnnotation;

/**
 * @Annotation
 * @Target("CLASS")
 */
#[Attribute(Attribute::TARGET_CLASS)]
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
                $events = array_map(fn ($item) => trim($item), $events);
            }

            $value['events'] = (array) $events;
        }

        parent::__construct($value);
    }
}
