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

!!! note

    You can also register doctrine migration commands,
    see the [store](./store.md#Migration) documentation for this.

## Migration commands

After the migration lib has been installed, the migration commands are automatically configured:

* ExecuteCommand: `event-sourcing:migrations:execute`
* GenerateCommand: `event-sourcing:migrations:generate`
* LatestCommand: `event-sourcing:migrations:latest`
* ListCommand: `event-sourcing:migrations:list`
* MigrateCommand: `event-sourcing:migrations:migrate`
* DiffCommand: `event-sourcing:migrations:diff`
* StatusCommand: `event-sourcing:migrations:status`
* VersionCommand: `event-sourcing:migrations:version`
