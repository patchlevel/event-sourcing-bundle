# Repository

A `repository` takes care of storing and loading the `aggregates`.
The [design pattern](https://martinfowler.com/eaaCatalog/repository.html) of the same name is also used.

Every aggregate needs a repository to be stored.
And each repository is only responsible for one aggregate.

## Explicit dependency injection

The service `@event_sourcing.repository.profile` with suffix `profile` is created magically from the configuration above.
In your own repository, use this configuration to auto-wire the PatchLevel repository accordingly to your aggregate.

```yaml
services:
    App\Domain\Hotel\HotelRepository:
        arguments:
            $repository: '@event_sourcing.repository.profile'
```
