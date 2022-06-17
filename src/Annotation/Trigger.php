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

#[Attribute(Attribute::TARGET_CLASS)]
class Trigger extends AbstractAnnotation
{
    public function __construct(
        public ?string $database = null,
        public string $table,
        public array $events = ['*'],
        public string $replication = 'default',
        public int $priority = 0
    ) {
    }
}
