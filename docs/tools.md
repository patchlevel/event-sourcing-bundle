# Tools

The bundle offers even more DX tools, which are listed here.

## Show events

You can display all events for a specific aggregate:

```bash
bin/console event-sourcing:show aggregate id
```

## Watch events

There is also a watch server, because you can start to see all events in real time. 
To do this, you have to add the following configuration for the dev environment:

```yaml
patchlevel_event_sourcing:
    watch_server:
        enabled: true
```

> :warning: Use this configuration for dev purposes only!

There is then a new command to start the watch server:

```bash
bin/console event-sourcing:watch
```

> :book: The command can be terminated with `ctrl+c` or `control+c`.