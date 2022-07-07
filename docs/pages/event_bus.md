# Event Bus

This library uses the core principle called [event bus](https://martinfowler.com/articles/201701-event-driven.html).

For all events that are persisted (when the `save` method has been executed on the [repository](./repository.md)),
the event will be dispatched to the `event bus`. All listeners are then called for each event.

!!! info

    You can find out more about the event bus in the library 
    [documentation](https://patchlevel.github.io/event-sourcing-docs/latest/event_bus/). 
    This documentation is limited to bundle integration.

## Symfony Event Bus

To use the [Symfony Messager](https://symfony.com/doc/current/messenger.html), 
you have to define it in messenger.yaml.
It's best to call the bus "event.bus".
An event bus can have 0 or n listener for an event. 
So "allow_no_handlers" has to be configured.

```yaml
framework:
    messenger:
        buses:
            event.bus:
                default_middleware: allow_no_handlers
```

We can then use this message or event bus in event sourcing:

```yaml
patchlevel_event_sourcing:
    event_bus:
        service: event.bus
```

## Command Bus

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
    otherwise they will be registered in both handlers.