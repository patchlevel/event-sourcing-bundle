# Snapshots

Some aggregates can have a large number of events.
This is not a problem if there are a few hundred.
But if the number gets bigger at some point, then loading and rebuilding can become slow.
The `snapshot` system can be used to control this.

Normally, the events are all executed again on the aggregate in order to rebuild the current state.
With a `snapshot`, we can shorten the way in which we temporarily save the current state of the aggregate.
When loading it is checked whether the snapshot exists.
If a hit exists, the aggregate is built up with the help of the snapshot.
A check is then made to see whether further events have existed since the snapshot
and these are then also executed on the aggregate.
Here, however, only the last events are loaded from the database and not all.

!!! info

    You can find out more about snapshots in the library 
    [documentation](https://patchlevel.github.io/event-sourcing-docs/latest/snapshots/). 
    This documentation is limited to bundle integration.

## Using Symfony Cache

You can use symfony cache to define the target of the snapshot store.

```yaml
framework:
    cache:
        default_redis_provider: 'redis://localhost'
        pools:
            event_sourcing.cache:
                adapter: cache.adapter.redis
```

After this, you need define the snapshot store. 
Symfony cache implement the psr6 interface, so we need choose this type
and enter the id from the cache service.

```yaml
patchlevel_event_sourcing:
    snapshot_stores:
        default:
            service: event_sourcing.cache
```

Finally, you have to tell the aggregate that it should use this snapshot store.

```php
namespace App\Domain\Profile;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Snapshot;

#[Aggregate(name: 'profile')]
#[Snapshot('default')]
final class Profile extends AggregateRoot
{
    // ...
}
```

!!! book

    You can find out more about the attributes [here](aggregate.md).

## Batch

So that not every write process also writes to the cache at the same time, 
you can also say from how many events should be written to the snapshot store first. 
This minimizes the write operations to the cache, which improves performance.

```php
namespace App\Domain\Profile;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Snapshot;

#[Aggregate(name: 'profile')]
#[Snapshot('default', batch: 1000)]
final class Profile extends AggregateRoot
{
    // ...
}
```