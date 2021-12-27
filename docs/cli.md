# CLI

The bundle also offers `cli commands` to create or delete `databases`. 
It is also possible to manage the `schema` and `projections`.

## Database commands

There are two commands for creating and deleting a database.

* `event-sourcing:database:create`
* `event-sourcing:database:drop`

## Schema commands

The database schema can also be created, updated and dropped.

* `event-sourcing:schema:create`
* `event-sourcing:schema:update`
* `event-sourcing:schema:drop`

> :book: You can also register doctrine migration commands,
> see the [store](./store.md#Migration) documentation for this.

## Projection commands

The creation, deletion and rebuilding of the projections is also possible via the cli.

* `event-sourcing:projection:create`
* `event-sourcing:projection:drop`
* `event-sourcing:projection:rebuild`

> :book: The [pipeline](./pipeline.md) will be used to rebuild the projection.
