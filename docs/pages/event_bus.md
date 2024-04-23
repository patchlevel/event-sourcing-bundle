# Event Bus

This library uses the core principle called [event bus](https://martinfowler.com/articles/201701-event-driven.html).

For all events that are persisted (when the `save` method has been executed on the [repository](./repository.md)),
the event will be dispatched to the `event bus`. All listeners are then called for each event.

!!! info

    You can find out more about the event bus in the library 
    [documentation](https://patchlevel.github.io/event-sourcing-docs/latest/event_bus/). 
    This documentation is limited to bundle integration.
    
## Event Bus Types

We supply our own event bus, but also have the option of replacing the event bus with an implementation.
We offer various options for this.

!!! note

    We recommend using the default event bus for better integration.
    
### Patchlevel (Default) Event Bus

First of all we have our own default event bus.
This works best with the library, as the `#[Subscribe]` attribute is used there, among other things.

```yaml
patchlevel_event_sourcing:
    event_bus:
        type: default
```
!!! note

    You don't have to specify this as it is the default value.
    
### Symfony Event Bus

But you can also use [Symfony Messenger](https://symfony.com/doc/current/messenger.html).
To do this, you first have to define a suitable message bus.
This must be "allow_no_handlers" so that this messenger can be an event bus according to the definition.

```yaml
# messenger.yaml
framework:
    messenger:
        buses:
            event.bus:
                default_middleware: allow_no_handlers
```
We can then use this messenger or event bus in event sourcing:

```yaml
patchlevel_event_sourcing:
    event_bus:
        service: event.bus
```
Since the event bus was replaced, event sourcing's own attributes no longer work.
You use the Symfony attributes instead.

```php
use Patchlevel\EventSourcing\EventBus\Message;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler('event.bus')]
class SmsNotificationHandler
{
    public function __invoke(Message $message): void
    {
        if (!$message instanceof GuestIsCheckedIn) {
            return;
        }

        // ... do some work - like sending an SMS message!
    }
}
```
#### Command Bus

If you use a command bus or cqrs as a pattern, then you should define a new message bus.
The whole thing can look like this:

```yaml
framework:
    messenger:
        default_bus: command.bus
        buses:
            command.bus: ~
            event.bus:
                default_middleware: allow_no_handlers
```
!!! warning

    You should deactivate the autoconfigure feature for the handlers, 
    otherwise they will be registered in both messenger.
    
### PSR-14 Event Bus

You can also use any other event bus that implements the [PSR-14](https://www.php-fig.org/psr/psr-14/) standard.

```yaml
patchlevel_event_sourcing:
    event_bus:
        type: psr14
        service: my.event.bus.service
```
!!! note

    Like the Symfony event bus, the event sourcing attributes no longer work here.
    You have to use the system that comes with the respective psr14 implementation.
    
### Custom Event Bus

You can also use your own event bus that implements the `Patchlevel\EventSourcing\EventBus\EventBus` interface.

```yaml
patchlevel_event_sourcing:
    event_bus:
        type: custom
        service: my.event.bus.service
```
!!! note

    Like the Symfony event bus, the event sourcing attributes no longer work here.
    You have to use the system that comes with the respective custom implementation.
    