[![Type Coverage](https://shepherd.dev/github/patchlevel/event-sourcing-bundle/coverage.svg)](https://shepherd.dev/github/patchlevel/event-sourcing-bundle)
[![Latest Stable Version](https://poser.pugx.org/patchlevel/event-sourcing-bundle/v)](//packagist.org/packages/patchlevel/event-sourcing-bundle)
[![License](https://poser.pugx.org/patchlevel/event-sourcing-bundle/license)](//packagist.org/packages/patchlevel/event-sourcing-bundle)

# Event-Sourcing-Bundle

A lightweight but also all-inclusive event sourcing bundle 
with a focus on developer experience and based on doctrine dbal.
This bundle is a [symfony](https://symfony.com/) integration 
for [event-sourcing](https://github.com/patchlevel/event-sourcing) library.

## Features

* Everything is included in the package for event sourcing
* Based on [doctrine dbal](https://github.com/doctrine/dbal) and their ecosystem
* Developer experience oriented and fully typed
* [Snapshots](docs/snapshots.md) system to quickly rebuild the aggregates
* [Pipeline](docs/pipeline.md) to build new [projections](docs/projection.md) or to migrate events
* [Scheme management](docs/store.md) and [doctrine migration](docs/store.md) support
* Dev [tools](docs/tools.md) such as a realtime event watcher
* Built in [cli commands](docs/cli.md) with [symfony](https://symfony.com/)

## Installation

```bash
composer require patchlevel/event-sourcing-bundle
```

> :warning: If you don't use the symfony flex recipe for this bundle, you need to follow
this [installation documentation](docs/installation.md).

## Documentation

// TODO

## Integration

* [Psalm](https://github.com/patchlevel/event-sourcing-psalm-plugin)