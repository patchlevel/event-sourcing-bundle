# Event-Sourcing-Bundle

A lightweight but also all-inclusive event sourcing bundle 
with a focus on developer experience and based on doctrine dbal.
This bundle is a [symfony](https://symfony.com/) integration 
for [event-sourcing](https://github.com/patchlevel/event-sourcing) library.

## Features

* Everything is included in the package for event sourcing
* Based on [doctrine dbal](https://github.com/doctrine/dbal) and their ecosystem
* Developer experience oriented and fully typed
* [Snapshots](snapshots.md) system to quickly rebuild the aggregates
* [Pipeline](pipeline.md) to build new [projections](projection.md) or to migrate events
* [Scheme management](store.md) and [doctrine migration](store.md) support
* Dev [tools](watch_server.md) such as a realtime event watcher
* Built in [cli commands](cli.md) with [symfony](https://symfony.com/)

## Installation

```bash
composer require patchlevel/event-sourcing-bundle
```

> :warning: If you don't use the symfony flex recipe for this bundle, you need to follow
this [installation documentation](installation.md).

## Integration

* [Psalm](https://github.com/patchlevel/event-sourcing-psalm-plugin)