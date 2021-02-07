[![Type Coverage](https://shepherd.dev/github/patchlevel/event-sourcing-bundle/coverage.svg)](https://shepherd.dev/github/patchlevel/event-sourcing-bundle)
[![Latest Stable Version](https://poser.pugx.org/patchlevel/event-sourcing-bundle/v)](//packagist.org/packages/patchlevel/event-sourcing-bundle)
[![License](https://poser.pugx.org/patchlevel/event-sourcing-bundle/license)](//packagist.org/packages/patchlevel/event-sourcing-bundle)

# event-sourcing-bundle

a symfony integration of a small lightweight [event-sourcing](https://github.com/patchlevel/event-sourcing) library.

## installation

```
composer require patchlevel/event-sourcing-bundle
```

## config

```
framework:
    messenger:
        buses:
            event.bus:
                default_middleware: allow_no_handlers
```

```
doctrine:
    dbal:
        connections:
            eventstore:
                url: '%env(EVENTSTORE_URL)%'
```

```
patchlevel_event_sourcing:
    store:
        dbal_connection: eventstore
        type: dbal_multi_table
    aggregates:
        App\Domain\Profile\Profile: profile
    message_bus: event.bus
```


## commands

### create schema

```
bin/console event-sourcing:schema:create
```

### update schema

```
bin/console event-sourcing:schema:update
```

### drop schema

```
bin/console event-sourcing:schema:update
```

### prepare projection

```
bin/console event-sourcing:projection:create
```

### drop projection

```
bin/console event-sourcing:projection:drop
```

### rebuild projection

```
bin/console event-sourcing:projection:rebuild
```

### watch server

dev config:

```
patchlevel_event_sourcing:
    watch_server:
        enabled: true
```

command:

```
bin/console event-sourcing:watch
```

### show events

```
bin/console event-sourcing:show aggregate id
```
