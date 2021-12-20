[![Type Coverage](https://shepherd.dev/github/patchlevel/event-sourcing-bundle/coverage.svg)](https://shepherd.dev/github/patchlevel/event-sourcing-bundle)
[![Latest Stable Version](https://poser.pugx.org/patchlevel/event-sourcing-bundle/v)](//packagist.org/packages/patchlevel/event-sourcing-bundle)
[![License](https://poser.pugx.org/patchlevel/event-sourcing-bundle/license)](//packagist.org/packages/patchlevel/event-sourcing-bundle)

# event-sourcing-bundle

a symfony integration of a small lightweight [event-sourcing](https://github.com/patchlevel/event-sourcing) library.

## installation

```
composer require patchlevel/event-sourcing-bundle
```

## configuration

### Define your aggregates with class namespace and the table name

Class `App\Domain\Profile\Profile` is from the [libraries example](https://github.com/patchlevel/event-sourcing#define-aggregates) and is using the table name `profile` 

```
patchlevel_event_sourcing:
    connection:
        url: '%env(EVENTSTORE_URL)%'
    store:
        type: multi_table
    aggregates:
        profile:
            class: App\Domain\Profile\Profile
```

### Define which repository the aggregates is using

The service `@event_sourcing.profile_repository` with prefix `profile` is created magically from the configuration above. 
In your own repository, use this configuration to auto-wire the PatchLevel repository accordingly to your aggregate. 

```
services:
    ...
    App\Infrastructure\EventSourcing\Repository\ProfileRepository:
      arguments:
        $repository: '@event_sourcing.repository.profile'
```

### Enable migrations

You can use doctrine migration to create and update the event store schema. For that you need to install the following package `doctrine/migrations`.
After it's installed you will have some new cli commands: `event-sourcing:migration:diff` and `event-sourcing:migration:migrate`. With these you can create new migrations files as a diff and execute them.
You can also change the namespace and the folder in the configuration.

```
patchlevel_event_sourcing:
    migration:
        namespace: EventSourcingMigrations
        path: "%kernel.project_dir%/migrations"
```

### Enable snapshots

You can define a snapshot store for individual aggregates. You can use symfony cache to define the target of the snapshotstore.

```
framework:
    cache:
        pools:
            event_sourcing.cache:
                adapter: cache.adapter.filesystem
```

After this, you need define the snapshot store. Symfony cache implement the psr6 interface, so we need choose this type
and enter the id from the cache service.

```
patchlevel_event_sourcing:
    snapshot_stores:
        default:
            service: event_sourcing.cache
```

Finally you have to tell the aggregate that it should use this snapshot store.

```
patchlevel_event_sourcing:
    aggregates:
        profile:
            class: App\Domain\Profile\Profile
            snapshot_store: default
```

## commands

### create database

```
bin/console event-sourcing:database:create
```

### drop database

```
bin/console event-sourcing:database:drop
```

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

## Pipeline

You can also manipulate events. The events are transferred from one database to another database. 
This ensures that the database is immutable and you can test the whole thing beforehand.

```php
<?php

declare(strict_types=1);

namespace App\Command;

use Patchlevel\EventSourcing\Pipeline\Middleware\ExcludeEventMiddleware;
use Patchlevel\EventSourcing\Pipeline\Middleware\RecalculatePlayheadMiddleware;
use Patchlevel\EventSourcing\Pipeline\Middleware\ReplaceEventMiddleware;
use Patchlevel\EventSourcing\Pipeline\Pipeline;
use Patchlevel\EventSourcing\Pipeline\Source\StoreSource;
use Patchlevel\EventSourcing\Pipeline\Target\StoreTarget;
use Patchlevel\EventSourcing\Store\PipelineStore;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class UpdateStoreCommand extends Command
{
    private PipelineStore $oldStore;
    private PipelineStore $newStore;

    public function __construct(PipelineStore $oldStore, PipelineStore $newStore)
    {
        parent::__construct();

        $this->oldStore = $oldStore;
        $this->newStore = $newStore;
    }

    protected function configure(): void
    {
        $this->setName('app:update-store');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $console = new SymfonyStyle($input, $output);
    
        $pipeline = new Pipeline(
            new StoreSource($this->oldStore),
            new StoreTarget($this->newStore),
            [
                new ExcludeEventMiddleware([EmailChanged::class]), // remove a event type
                new ReplaceEventMiddleware(UserCreated::class, static function (UserCreated $event) {
                    return UserRegistered::raise(
                        $event->profileId(),
                        $event->email(),
                    );
                }), // replace a old event with another one
                new RecalculatePlayheadMiddleware(), // recalculate playhead (because we remove an event type)
            ]
        );

        $console->progressStart($pipeline->count());

        $pipeline->run(static function () use ($console): void {
            $console->progressAdvance();
        });

        $console->progressFinish();
        $console->success('finish');

        return 0;
    }
}
```
