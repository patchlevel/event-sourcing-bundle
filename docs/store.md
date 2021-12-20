
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
