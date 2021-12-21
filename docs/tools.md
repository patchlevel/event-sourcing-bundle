# Tools

## Show events

```bash
bin/console event-sourcing:show aggregate id
```

## Watch events

dev config:

```yaml
patchlevel_event_sourcing:
    watch_server:
        enabled: true
```

```bash
bin/console event-sourcing:watch
```