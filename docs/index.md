# Event-Sourcing-Bundle

An event sourcing bundle, complete with all the essential features,
powered by the reliable Doctrine ecosystem and focused on developer experience.
This bundle is a [symfony](https://symfony.com/) integration
for [event-sourcing](https://github.com/patchlevel/event-sourcing) library.

## Features

* Everything is included in the package for event sourcing
* Based on [doctrine dbal](https://github.com/doctrine/dbal) and their ecosystem
* Developer experience oriented and fully typed
* Automatic [snapshot](/docs/event-sourcing/latest/snapshots)-system to boost your performance
* [Split](/docs/event-sourcing/latest/split-stream) big aggregates into multiple streams
* Versioned and managed lifecycle of [subscriptions](/docs/event-sourcing/latest/subscription) like projections and processors
* Safe usage of [Personal Data](/docs/event-sourcing/latest/personal-data) with crypto-shredding
* Smooth [upcasting](/docs/event-sourcing/latest/upcasting) of old events
* Simple setup with [scheme management](/docs/event-sourcing/latest/store) and [doctrine migration](/docs/event-sourcing/latest/store)
* Built in [cli commands](/docs/event-sourcing/latest/cli) with [symfony](https://symfony.com/)
* and much more...

## Installation

```bash
composer require patchlevel/event-sourcing-bundle
```

:::note
If you don't use the symfony flex recipe for this bundle, you need to follow
this [installation documentation](installation.md).
:::

:::tip
Start with the [quickstart](getting-started.md) to get a feeling for the bundle.
:::

## Integration

* [Psalm](https://github.com/patchlevel/event-sourcing-psalm-plugin)
* [Admin Bundle](https://github.com/patchlevel/event-sourcing-admin-bundle)
