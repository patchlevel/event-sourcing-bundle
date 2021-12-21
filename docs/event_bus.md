# Event Bus

This library uses the core principle called [event bus](https://martinfowler.com/articles/201701-event-driven.html).

For all events that are persisted (when the `save` method has been executed on the [repository](./repository.md)),
the event will be dispatched to the `event bus`. All listeners are then called for each event.

## Symfony Event Bus

```yaml
framework:
    messenger:
        buses:
            event.bus:
                default_middleware: allow_no_handlers
```

```yaml
patchlevel_event_sourcing:
    event_bus:
        service: event.bus
```

## Command Bus

```yaml
framework:
    messenger:
        default_bus: command.bus
        buses:
            command.bus: ~
            event.bus:
                default_middleware: allow_no_handlers
```