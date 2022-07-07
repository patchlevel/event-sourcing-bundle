# Store

In the end, the events have to be saved somewhere.
The library is based on [doctrine dbal](https://www.doctrine-project.org/projects/dbal.html)
and offers two different store strategies.

!!! info

    You can find out more about stores in the library 
    [documentation](https://patchlevel.github.io/event-sourcing-docs/latest/store/). 
    This documentation is limited to bundle integration.

## Store types

We offer two store strategies that you can choose as you like.

### Single Table Store

With the `single_table` everything is saved in one table.

```yaml
patchlevel_event_sourcing:
    store:
        type: single_table
```

!!! note

    You can switch between strategies using the [pipeline](./pipeline.md).

### Multi Table Store

With the `multi_table` a separate table is created for each aggregate type.
In addition, a meta table is created by referencing all events in the correct order.

```yaml
patchlevel_event_sourcing:
    store:
        type: multi_table
```

!!! note

    You can switch between strategies using the [pipeline](./pipeline.md).

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

    You should avoid that this connection or database is used by other tools or libraries.
    Create for e.g. doctrine orm its own database and connection.

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
