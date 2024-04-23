# Event-Sourcing-Bundle

An event sourcing bundle, complete with all the essential features,
powered by the reliable Doctrine ecosystem and focused on developer experience.
This bundle is a [symfony](https://symfony.com/) integration
for [event-sourcing](https://github.com/patchlevel/event-sourcing) library.

## Features

* Everything is included in the package for event sourcing
* Based on [doctrine dbal](https://github.com/doctrine/dbal) and their ecosystem
* Developer experience oriented and fully typed
* Automatic [snapshot](https://patchlevel.github.io/event-sourcing-docs/latest/snapshots/)-system to boost your performance
* [Split](https://patchlevel.github.io/event-sourcing-docs/latest/split_stream/) big aggregates into multiple streams
* Versioned and managed lifecycle of [subscriptions](https://patchlevel.github.io/event-sourcing-docs/latest/subscription/) like projections and processors
* Safe usage of [Personal Data](https://patchlevel.github.io/event-sourcing-docs/latest/personal_data/) with crypto-shredding
* Smooth [upcasting](https://patchlevel.github.io/event-sourcing-docs/latest/upcasting/) of old events
* Simple setup with [scheme management](https://patchlevel.github.io/event-sourcing-docs/latest/store/) and [doctrine migration](https://patchlevel.github.io/event-sourcing-docs/latest/store/)
* Built in [cli commands](https://patchlevel.github.io/event-sourcing-docs/latest/cli/) with [symfony](https://symfony.com/)
* and much more...

## Installation

```bash
composer require patchlevel/event-sourcing-bundle
```
!!! info

    If you don't use the symfony flex recipe for this bundle, you need to follow
    this [installation documentation](installation.md).
    
!!! tip

    Start with the [quickstart](./getting_started.md) to get a feeling for the bundle.
    
## Integration

* [Psalm](https://github.com/patchlevel/event-sourcing-psalm-plugin)
* [Admin Bundle](https://github.com/patchlevel/event-sourcing-admin-bundle)
