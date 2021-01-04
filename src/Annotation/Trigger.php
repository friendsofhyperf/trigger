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

use Hyperf\Di\Annotation\AbstractAnnotation;

/**
 * @Annotation
 * @Target("CLASS")
 */
class Trigger extends AbstractAnnotation
{
    /**
     * @var string
     */
    public $replication = 'default';

    /**
     * @var array
     */
    public $events = [];

    /**
     * @var string
     */
    public $table;

    /**
     * @var int
     */
    public $priority = 0;

    public function __construct($value = null)
    {
        if (isset($value['on'])) {
            if ($value['on'] == '*') {
                $value['on'] = ['write', 'update', 'delete'];
            }

            if (is_string($value['on']) && stripos($value['on'], ',')) {
                $value['on'] = explode(',', $value['on']);
            }

            $this->events = (array) $value['on'];
        }

        if (isset($value['replication']) && is_string($value['replication'])) {
            $this->replication = $value['replication'];
        }

        if (isset($value['table']) && is_string($value['table'])) {
            $this->table = $value['table'];
        }

        if (isset($value['priority']) && is_numeric($value['priority'])) {
            $this->priority = $value['priority'];
        }
    }
}
