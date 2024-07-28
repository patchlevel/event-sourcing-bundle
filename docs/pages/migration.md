# Migration

You can use doctrine migration, which is known from doctrine orm, to create your schema and keep it in sync.

!!! info

    You can find out more about migration in the library 
    [documentation](https://event-sourcing.patchlevel.io/latest/migration/). 
    This documentation is limited to bundle integration.


## Installation

In order to be able to use `doctrine/migrations`,
you have to install the associated package.

```bash
composer require doctrine/migrations
```

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

## Configure

You can also configure the migration integration. 
Here is an example with the default values:

```yaml
patchlevel_event_sourcing:
    migration:
        namespace: 'EventSourcingMigrations'
        path: '%kernel.project_dir%/migrations'
```
