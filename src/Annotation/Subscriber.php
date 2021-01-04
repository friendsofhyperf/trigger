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
class Subscriber extends AbstractAnnotation
{
    /**
     * @var string
     */
    public $replication = 'default';

    /**
     * @var int
     */
    public $priority = 0;

    public function __construct($value = null)
    {
        if (isset($value['replication']) && is_string($value['replication'])) {
            $this->replication = $value['replication'];
        }

        if (isset($value['priority']) && is_numeric($value['priority'])) {
            $this->priority = $value['priority'];
        }
    }
}
