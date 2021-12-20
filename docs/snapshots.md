

### Enable snapshots

You can define a snapshot store for individual aggregates. You can use symfony cache to define the target of the snapshotstore.

```
framework:
    cache:
        pools:
            event_sourcing.cache:
                adapter: cache.adapter.filesystem
```

After this, you need define the snapshot store. Symfony cache implement the psr6 interface, so we need choose this type
and enter the id from the cache service.

```
patchlevel_event_sourcing:
    snapshot_stores:
        default:
            service: event_sourcing.cache
```

Finally you have to tell the aggregate that it should use this snapshot store.

```
patchlevel_event_sourcing:
    aggregates:
        profile:
            class: App\Domain\Profile\Profile
            snapshot_store: default
```