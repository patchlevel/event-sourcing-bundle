[![Type Coverage](https://shepherd.dev/github/patchlevel/event-sourcing-bundle/coverage.svg)](https://shepherd.dev/github/patchlevel/event-sourcing-bundle)
[![Latest Stable Version](https://poser.pugx.org/patchlevel/event-sourcing-bundle/v)](//packagist.org/packages/patchlevel/event-sourcing-bundle)
[![License](https://poser.pugx.org/patchlevel/event-sourcing-bundle/license)](//packagist.org/packages/patchlevel/event-sourcing-bundle)

# Event-Sourcing-Bundle

An event sourcing bundle, complete with all the essential features,
powered by the reliable Doctrine ecosystem and focused on developer experience.
This bundle is a [symfony](https://symfony.com/) integration 
for [event-sourcing](https://github.com/patchlevel/event-sourcing) library.

## Features

* Everything is included in the package for event sourcing
* Based on [doctrine dbal](https://github.com/doctrine/dbal) and their ecosystem
* Developer experience oriented and fully typed
* Automatic [snapshot](https://event-sourcing.patchlevel.io/latest/snapshots/)-system to boost your performance
* [Split](https://event-sourcing.patchlevel.io/latest/split_stream/) big aggregates into multiple streams
* Versioned and managed lifecycle of [subscriptions](https://event-sourcing.patchlevel.io/latest/subscription/) like projections and processors
* Safe usage of [Personal Data](https://event-sourcing.patchlevel.io/latest/personal_data/) with crypto-shredding
* Smooth [upcasting](https://event-sourcing.patchlevel.io/latest/upcasting/) of old events
* Simple setup with [scheme management](https://event-sourcing.patchlevel.io/latest/store/) and [doctrine migration](https://event-sourcing.patchlevel.io/latest/store/)
* Built in [cli commands](https://event-sourcing.patchlevel.io/latest/cli/)
* and much more...

## Installation

```bash
composer require patchlevel/event-sourcing-bundle
```

> [!WARNING]
> If you don't use the symfony flex recipe for this bundle, you need to follow
this [installation documentation](https://event-sourcing-bundle.patchlevel.io/latest/installation/).

## Documentation

* [Bundle Documentation](https://event-sourcing-bundle.patchlevel.io/latest/)
* [Library Documentation](https://event-sourcing.patchlevel.io/latest/)
* [Related Blog](https://patchlevel.de/blog)

## Integration

* [Psalm](https://github.com/patchlevel/event-sourcing-psalm-plugin)

## Sponsors

[<img src="https://github.com/patchlevel/event-sourcing/assets/470138/d00b7459-23b7-431b-80b4-93cfc1b66216" alt="blackfire" width="200">](https://www.blackfire.io)
