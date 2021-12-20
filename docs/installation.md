
### Define your aggregates with class namespace and the table name

Class `App\Domain\Profile\Profile` is from the [libraries example](https://github.com/patchlevel/event-sourcing#define-aggregates) and is using the table name `profile`

```
patchlevel_event_sourcing:
    connection:
        url: '%env(EVENTSTORE_URL)%'
    store:
        type: multi_table
    aggregates:
        profile:
            class: App\Domain\Profile\Profile
```
