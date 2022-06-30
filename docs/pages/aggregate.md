# Aggregate

!!! info

    You can find out more about aggregates in the library 
    [documentation](https://patchlevel.github.io/event-sourcing-docs/latest/aggregates/). 
    This documentation is limited to bundle integration.

## Register aggregates

So that the necessary service such as the repositories are available, 
you have to register all aggregates.

You can do this in the Yaml definition by listing all aggregates with names and the associated class.

```yaml
patchlevel_event_sourcing:
  aggregates: '%kernel.project_dir%/src'
```

!!! note

    You can also define multiple paths by specifying an array.

So that the bundle knows where to look, you also have to specify a path.

Here you have to give all aggregates the `Aggregate` attribute and give the associated name.

```php
namespace App\Domain\Hotel;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;

#[Aggregate(name: 'hotel')]
final class Hotel extends AggregateRoot
{
    // ...
}
```

## Use snapshots

You can also tell each aggregate that it should use snapshots 
so that the rebuilding of the state is faster.

To do this, a snapshot store must first be defined. 
You can read that [here](snapshots.md).

And then you can define the snapshot on the aggregates.

```yaml
patchlevel_event_sourcing:
  aggregates:
    hotel:
      class: App\Domain\Hotel\Hotel
      snapshot: default
```

If you are using attributes then you have to put the snapshot there.

```php
namespace App\Domain\Hotel;

use Patchlevel\EventSourcing\Aggregate\SnapshotableAggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Snapshot;

#[Aggregate(name: 'hotel')]
#[Snapshot('default')]
final class Hotel extends SnapshotableAggregateRoot
{
   // ...
}
```