<?php

declare(strict_types=1);
/**
 * This file is part of hyperf-trigger.
 *
 * @link     https://github.com/friendsofhyperf/trigger
 * @document https://github.com/friendsofhyperf/trigger/blob/main/README.md
 * @contact  huangdijia@gmail.com
 */
namespace FriendsOfHyperf\Trigger\Contact;

interface TriggerInterface
{
    public function onWrite(array $new);

    public function onUpdate(array $old, array $new);

    public function onDelete(array $old);
}
