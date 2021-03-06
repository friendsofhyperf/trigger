# Trigger

[![Open in Visual Studio Code](https://open.vscode.dev/badges/open-in-vscode.svg)](https://open.vscode.dev/friendsofhyperf/trigger)
[![Latest Stable Version](https://poser.pugx.org/friendsofhyperf/trigger/version.png)](https://packagist.org/packages/friendsofhyperf/trigger)
[![Total Downloads](https://poser.pugx.org/friendsofhyperf/trigger/d/total.png)](https://packagist.org/packages/friendsofhyperf/trigger)
[![GitHub license](https://img.shields.io/github/license/friendsofhyperf/trigger)](https://github.com/friendsofhyperf/trigger)

MySQL trigger component for hyperf, Based on a great work of creators：[krowinski/php-mysql-replication](https://github.com/krowinski/php-mysql-replication)

## Installation

- Request

```bash
composer require "friendsofhyperf/trigger"
```

- Publish

```bash
php bin/hyperf.php vendor:publish friendsofhyperf/trigger
```

## Define a trigger

```php
namespace App\Trigger;

use FriendsOfHyperf\Trigger\Annotation\Trigger;
use FriendsOfHyperf\Trigger\Trigger\AbstractTrigger;
use MySQLReplication\Event\DTO\EventDTO;

/**
 * single
 * @Trigger(table="table", on="write", replication="default")
 * or multi events by array
 * @Trigger(table="table", on={"write", "update", "delete"}, replication="default")
 * or multi events by string
 * @Trigger(table="table", on="write,update,delete", replication="default")
 * or all events
 * @Trigger(table="table", on="*", replication="default")
 */
class SomeTableTrigger extends AbstractTrigger
{
    public function onWrite(array $new)
    {
        var_dump($new);
    }

    public function onUpdate(array $old, array $new)
    {
        var_dump($old, $new);
    }

    public function onDelete(array $old)
    {
        var_dump($old);
    }
}
```

## Define a subscriber

```php
namespace App\Subscriber;

use FriendsOfHyperf\Trigger\Annotation\Subscriber;
use FriendsOfHyperf\Trigger\Subscriber\AbstractEventSubscriber;
use MySQLReplication\Event\DTO\EventDTO;

/**
 * @Subscriber(replication="default")
 */
class DemoSubscriber extends AbstractEventSubscriber
{
    protected function allEvents(EventDTO $event): void
    {
        // some code
    }
}
```

## Setup Process

- Default

```php
namespace App\Process;

use FriendsOfHyperf\Trigger\Process\ConsumeProcess;
use Hyperf\Process\Annotation\Process;

/**
 * @Process
 */
class TriggerProcess extends ConsumeProcess
{
}
```

- Custom replication

```php
namespace App\Process;

use FriendsOfHyperf\Trigger\Process\ConsumeProcess;
use Hyperf\Process\Annotation\Process;

/**
 * @Process
 */
class CustomProcess extends ConsumeProcess
{
    protected $replication = 'custom_replication';
}
```

## Signal

```php
// config/autoload/signal.php
return [
    FriendsOfHyperf\Trigger\Handler\TriggerStopHandler::class => PHP_INT_MAX,
];
```
