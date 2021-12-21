# Store

In the end, the events have to be saved somewhere.
The library is based on [doctrine dbal](https://www.doctrine-project.org/projects/dbal.html)
and offers two different store strategies.

## Store types

We offer two store strategies that you can choose as you like.

### Single Table Store

With the `single_table` everything is saved in one table.

```yaml
patchlevel_event_sourcing:
    store:
        type: single_table
```

> :book: You can switch between strategies using the [pipeline](./pipeline.md).

### Multi Table Store

With the `multi_table` a separate table is created for each aggregate type.
In addition, a meta table is created by referencing all events in the correct order.

```yaml
patchlevel_event_sourcing:
    store:
        type: multi_table
```

> :book: You can switch between strategies using the [pipeline](./pipeline.md).

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
