# Watch Server

There is also a watch server, because you can start to see all events in real time. 

!!! info

    You can find out more about the watch server in the library 
    [documentation](https://patchlevel.github.io/event-sourcing-docs/latest/watch_server/). 
    This documentation is limited to bundle integration.

## Configuration

To do this, you have to add the following configuration for the dev environment:

```yaml
patchlevel_event_sourcing:
    watch_server:
        enabled: true
```

!!! warning

    Use this configuration for dev purposes only!

## Watch

There is then a new command to start the watch server:

```bash
bin/console event-sourcing:watch
```

!!! note

    The command can be terminated with `ctrl+c` or `control+c`.