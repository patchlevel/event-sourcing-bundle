# Events

Events are used to describe things that happened in the application. 
Since the events already happened, they are also immutable. 
In event sourcing, these are used to save and rebuild the current state. 
You can also listen on events to react and perform different actions.

!!! info

    You can find out more about events in the library 
    [documentation](https://event-sourcing.patchlevel.io/latest/events/). 
    This documentation is limited to bundle integration.

## Register events

A path must be specified for Event Sourcing to know where to look for your evets.

```yaml
patchlevel_event_sourcing:
  events: '%kernel.project_dir%/src'
```

!!! tip

    You can also define multiple paths by specifying an array.

## Define events

Next, you need to create a class to serve as an event.
This class must get the `Event` attribute with a unique event name.

```php
namespace App\Domain\Hotel\Event;

use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Attribute\Normalize;
use Patchlevel\EventSourcingBundle\Normalizer\UuidNormalizer;
use Symfony\Component\Uid\Uuid;

#[Event('hotel.created')]
final class HotelCreated
{
    public function __construct(
        #[Normalize(new UuidNormalizer())]
        public readonly Uuid $id, 
        public readonly string $hotelName
    ) {
    }
}
```

!!! note

    If you want to learn more about events, read the [library documentation](https://event-sourcing.patchlevel.io/latest/events/).

!!! note

    You can read more about normalizer [here](normalizer.md).