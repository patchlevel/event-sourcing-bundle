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
* [Snapshots](https://patchlevel.github.io/event-sourcing-bundle-docs/latest/installation/) system to quickly rebuild the aggregates
* [Pipeline](https://patchlevel.github.io/event-sourcing-bundle-docs/latest/pipeline/) to build new [projections](https://patchlevel.github.io/event-sourcing-bundle-docs/latest/projection/) or to migrate events
* [Scheme management](https://patchlevel.github.io/event-sourcing-bundle-docs/latest/store/) and [doctrine migration](https://patchlevel.github.io/event-sourcing-bundle-docs/latest/migration/) support
* Dev [tools](https://patchlevel.github.io/event-sourcing-bundle-docs/latest/watch_server/) such as a realtime event watcher
* Built in [cli commands](https://patchlevel.github.io/event-sourcing-bundle-docs/latest/cli/)

## Installation

```bash
composer require patchlevel/event-sourcing-bundle
```

> :warning: If you don't use the symfony flex recipe for this bundle, you need to follow
this [installation documentation](https://patchlevel.github.io/event-sourcing-bundle-docs/latest/installation/).

## Documentation

* [Getting Started](https://patchlevel.github.io/event-sourcing-bundle-docs/latest/getting_started/)
* [Documentation](https://patchlevel.github.io/event-sourcing-bundle-docs/latest/)

## Integration

* [Psalm](https://github.com/patchlevel/event-sourcing-psalm-plugin)