# Store

In the end, the events have to be saved somewhere.
The library is based on [doctrine dbal](https://www.doctrine-project.org/projects/dbal.html)
and offers two different store strategies.

!!! info

    You can find out more about stores in the library 
    [documentation](https://patchlevel.github.io/event-sourcing-docs/latest/store/). 
    This documentation is limited to bundle integration.

## Manage database and schema

The bundle provides you with a few `commands` with which you can create and delete the `database`. 
You can also use it to create, edit and delete the `schema`.

### Create and drop database

```bash
bin/console event-sourcing:database:create
bin/console event-sourcing:database:drop
```

### Create, update and drop schema

```bash
bin/console event-sourcing:schema:create
bin/console event-sourcing:schema:udapte
bin/console event-sourcing:schema:drop
```

## Use doctrine connection

If you have installed the [doctrine bundle](https://github.com/doctrine/DoctrineBundle), 
you can also define the connection via doctrine and then use it in the store.

```yaml
doctrine:
    dbal:
        connections:
            eventstore:
                url: '%env(EVENTSTORE_URL)%'

patchlevel_event_sourcing:
    connection:
        service: doctrine.dbal.eventstore_connection
```

!!! warning

    If you want to use the same connection as doctrine orm, then you have to set the flag `merge_orm_schema`. 
    Otherwise you should avoid using the same connection as other tools.

!!! note

    You can find out more about the dbal configuration 
    [here](https://symfony.com/bundles/DoctrineBundle/current/configuration.html).

## Migration

You can also manage your schema with doctrine migrations.

In order to be able to use `doctrine/migrations`,
you have to install the associated package.

```bash
composer require doctrine/migrations
```

With this package, further commands are available such as. 
for creating and executing migrations.

```bash
bin/console event-sourcing:migration:diff
bin/console event-sourcing:migration:migrate
```

You can also change the namespace and the folder in the configuration.

```yaml
patchlevel_event_sourcing:
    migration:
        namespace: EventSourcingMigrations
        path: "%kernel.project_dir%/migrations"
```


## Merge ORM Schema

You can also merge the schema with doctrine orm. You have to set the following flag for this:

```yaml
patchlevel_event_sourcing:
    store:
        merge_orm_schema: true
```

!!! note

    All schema relevant commands are removed if you activate this option. You should use the doctrine commands then.

!!! warning

    If you want to merge the schema, then the same doctrine connection must be used as with the doctrine orm. 
    Otherwise errors may occur!