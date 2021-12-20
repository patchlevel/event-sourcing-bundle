
### Define which repository the aggregates is using

The service `@event_sourcing.profile_repository` with prefix `profile` is created magically from the configuration above.
In your own repository, use this configuration to auto-wire the PatchLevel repository accordingly to your aggregate.

```
services:
    ...
    App\Infrastructure\EventSourcing\Repository\ProfileRepository:
      arguments:
        $repository: '@event_sourcing.repository.profile'
```
