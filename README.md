[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fpatchlevel%2Fevent-sourcing%2Fmaster)](https://dashboard.stryker-mutator.io/reports/github.com/patchlevel/event-sourcing/master)
[![Type Coverage](https://shepherd.dev/github/patchlevel/event-sourcing-bundle/coverage.svg)](https://shepherd.dev/github/patchlevel/event-sourcing)
[![Latest Stable Version](https://poser.pugx.org/patchlevel/event-sourcing-bundle/v)](//packagist.org/packages/patchlevel/event-sourcing)
[![License](https://poser.pugx.org/patchlevel/event-sourcing-bundle/license)](//packagist.org/packages/patchlevel/event-sourcing)

# event-sourcing

Small lightweight event-sourcing library.

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
    aggregates:
        App\Domain\Profile\Profile: profile
    message_bus: event.bus
```
