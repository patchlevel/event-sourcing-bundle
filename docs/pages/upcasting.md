# Upcasting

There are cases where the already have events in our stream but there is data missing or not in the right format for our
new usecase. Normally you would need to create versioned events for this. This can lead to many versions of the same
event which could lead to some chaos. To prevent this we offer `Upcaster`, which can operate on the payload before
denormalizing to an event object. There you can change the event name and adjust the payload of the event.

!!! info

    You can find out more about upcasting in the library 
    [documentation](https://patchlevel.github.io/event-sourcing-docs/latest/upcasting/). 
    This documentation is limited to bundle integration.

## Usage

```php
use Patchlevel\EventSourcing\Serializer\Upcast\Upcast;
use Patchlevel\EventSourcing\Serializer\Upcast\Upcaster;

final class ProfileCreatedEmailLowerCastUpcaster implements Upcaster
{
    public function __invoke(Upcast $upcast): Upcast
    {
        // ignore if other event is processed
        if ($upcast->eventName !== 'profile_created') {
            return $upcast;
        }
        
        return $upcast->replacePayloadByKey('email', strtolower($upcast->payload['email']));
    }
}
```

If you have the symfony default service setting with `autowire`and `autoconfigure` enabled,
the upcaster is automatically recognized and registered at the `Upcaster` interface.
Otherwise you have to define the upcaster in the symfony service file:

```yaml
services:
    App\Upcaster\ProfileCreatedEmailLowerCastUpcaster:
      tags:
        - event_sourcing.upcaster
```