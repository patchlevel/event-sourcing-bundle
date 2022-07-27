# Aggregate

!!! info

    You can find out more about aggregates in the library 
    [documentation](https://patchlevel.github.io/event-sourcing-docs/latest/aggregate/). 
    This documentation is limited to bundle integration.

## Register aggregates

A path must be specified for Event Sourcing to know where to look for your aggregates.

```yaml
patchlevel_event_sourcing:
  aggregates: '%kernel.project_dir%/src'
```

!!! tip

    You can also define multiple paths by specifying an array.

## Define aggregates

Next, you need to create a class to serve as an aggregate. 
In our example it is a hotel. This class must inherit from `AggregateRoot` and get the `Aggregate` attribute.

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

!!! note

    You should read [here](https://patchlevel.github.io/event-sourcing-docs/latest/aggregates/) how the aggregates then work internally.
