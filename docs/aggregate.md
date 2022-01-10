# Aggregate

> Aggregate is a pattern in Domain-Driven Design. A DDD aggregate is a cluster of domain objects
> that can be treated as a single unit. [...]
>
> :book: [DDD Aggregate - Martin Flower](https://martinfowler.com/bliki/DDD_Aggregate.html)

You can find out more about the aggregates in the library [documentation](https://github.com/patchlevel/event-sourcing#documentation). 
This documentation is limited to bundle integration.

## Register aggregates

So that the necessary service such as the repositories are available, 
you have to register all aggregates.

You can do this in the Yaml definition by listing all aggregates with names and the associated class.

```yaml
patchlevel_event_sourcing:
  aggregates:
    hotel:
      class: App\Domain\Hotel\Hotel
```

Or you use the attribute variant (since v1.2). 
Here you have to give all aggregates the `Aggregate` attribute and give the associated name.

```php
namespace App\Domain\Hotel;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcingBundle\Attribute\Aggregate;

#[Aggregate(name: 'hotel')]
final class Hotel extends AggregateRoot
{
    // ...
}
```

So that the bundle knows where to look, you also have to specify a path.

```yaml
patchlevel_event_sourcing:
  aggregates_paths: '%kernel.project_dir%/src'
```

> :book: You can also define multiple paths by specifying an array.

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
use Patchlevel\EventSourcingBundle\Attribute\Aggregate;

#[Aggregate(name: 'hotel', snapshotStore: 'default')]
final class Hotel extends SnapshotableAggregateRoot
{
   // ...
}
```