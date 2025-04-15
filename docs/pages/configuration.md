# Configuration

!!! info

    You can find out more about event sourcing in the library 
    [documentation](https://event-sourcing.patchlevel.io/latest/). 
    This documentation is limited to bundle integration and configuration.
    
!!! tip

    We provide a [default configuration](./installation.md#configuration-file) that should work for most projects.
    
## Aggregate

A path must be specified for Event Sourcing to know where to look for your aggregates.
If you want you can use glob patterns to specify multiple paths.

```yaml
patchlevel_event_sourcing:
  aggregates: '%kernel.project_dir%/src/*/Domain'
```
Or use an array to specify multiple paths.

```yaml
patchlevel_event_sourcing:
  aggregates:
    - '%kernel.project_dir%/src/Hotel/Domain'
    - '%kernel.project_dir%/src/Room/Domain'
```
!!! note

    The library will automatically register all classes marked with the `#[Aggregate]` attribute in the specified paths.
    
!!! tip

    If you want to learn more about aggregates, read the [library documentation](https://event-sourcing.patchlevel.io/latest/aggregate/).
    
## Events

A path must be specified for Event Sourcing to know where to look for your events.
If you want you can use glob patterns to specify multiple paths.

```yaml
patchlevel_event_sourcing:
  events: '%kernel.project_dir%/src/*/Domain/Event'
```
Or use an array to specify multiple paths.

```yaml
patchlevel_event_sourcing:
  events:
    - '%kernel.project_dir%/src/Hotel/Domain/Event'
    - '%kernel.project_dir%/src/Room/Domain/Event'
```
!!! tip

    If you want to learn more about events, read the [library documentation](https://event-sourcing.patchlevel.io/latest/events/).
    
## Custom Headers

If you want to implement custom headers for your application, you must specify the
paths to look for those headers.
If you want you can use glob patterns to specify multiple paths.

```yaml
patchlevel_event_sourcing:
  headers: '%kernel.project_dir%/src/*/Domain/Header'
```
Or use an array to specify multiple paths.

```yaml
patchlevel_event_sourcing:
  headers:
    - '%kernel.project_dir%/src/Hotel/Domain/Header'
    - '%kernel.project_dir%/src/Room/Domain/Header'
```
!!! tip

    If you want to learn more about custom headers, read the [library documentation](https://event-sourcing.patchlevel.io/latest/message/#custom-headers).
    
## Connection

You have to specify the connection url to the event store.

```yaml
patchlevel_event_sourcing:
  connection:
    url: '%env(EVENTSTORE_URL)%'
```
!!! note

    You can find out more about how to create a connection 
    [here](https://www.doctrine-project.org/projects/doctrine-dbal/en/latest/reference/configuration.html)
    
### Connection for Projections

Per default, our event sourcing connection is not available to use in your application.
But you can create a dedicated connection that you can use for your projections.

```yaml
patchlevel_event_sourcing:
  connection:
    url: '%env(EVENTSTORE_URL)%'
    provide_dedicated_connection: true
```
!!! warning

    If you use doctrine migrations, you should exclude you projection tables from the schema generation.
    The schema is managed by the subscription engine and should not be managed by doctrine.
    
!!! tip

    You can autowire the connection in your services like this:
    
    ```php
    use Doctrine\DBAL\Connection;
    
    public function __construct(
        private readonly Connection $projectionConnection,
    ) {
    }
    ```
    
### Doctrine Bundle

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
!!! danger

    Do not use the same connection for event sourcing and your projections,
    otherwise you may run into transaction problems.
    
!!! warning

    If you want to use the same connection as doctrine orm, then you have to set the flag `merge_orm_schema`. 
    Otherwise you should avoid using the same connection as other tools.
    
!!! note

    You can find out more about the dbal configuration 
    [here](https://symfony.com/bundles/DoctrineBundle/current/configuration.html).
    
If you are using Doctrine for your projections too, you need to create a dedicated connection for this.
You can do this by defining a new connection named `projection` in the `doctrine.yaml` file
and use the same connection url as for the event store.

```yaml
doctrine:
    dbal:
        connections:
            eventstore:
                url: '%env(EVENTSTORE_URL)%'
            projection:
                url: '%env(EVENTSTORE_URL)%'

patchlevel_event_sourcing:
    connection:
        service: doctrine.dbal.eventstore_connection
```
!!! warning

    You should exclude your projection tables from the schema generation.
    
    ```yaml
    doctrine:
        dbal:
            schema_filter: ~^(projection_)~
    ```
    
Then you can use this connection in your projections.
If you are using autowiring you can inject the right connection `Connection $projectionConnection` parameter name.
The prefix `projection` is used to identify the connection.

```php
namespace App\Projection;

use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\Attribute\Projector;

#[Projector('my_projection')]
class MyProjection
{
    public function __construct(
        private readonly Connection $projectionConnection,
    ) {
    }
}
```
## Store

The store and schema is configurable.

### Change Store type

You can change the store type of the event store.

```yaml
patchlevel_event_sourcing:
    store:
        type: 'in_memory'
```
Following store types are available:

- `dbal_aggregate` *default*
- `dbal_stream` *experimental*
- `in_memory`
- `custom`

!!! note

    If you use `custom` store type, you need to set the service id under `patchlevel_event_sourcing.store.service`.
    
### Change table Name

You can change the table name of the event store.

```yaml
patchlevel_event_sourcing:
    store:
        options:
            table_name: 'my_event_store'
```
### Read Only Mode

For `dbal_aggregate` and `dbal_stream` store types you can activate the read only mode.
Readings are possible, but if you try to write, an exception `StoreIsReadOnly` is thrown.

```yaml
patchlevel_event_sourcing:
    store:
        read_only: true
```
!!! tip

    This is useful if you have maintenance work on the event store and you want to avoid side effects.
    
### Merge ORM Schema

You can also merge the schema with doctrine orm. You have to set the following flag for this:

```yaml
patchlevel_event_sourcing:
    store:
        merge_orm_schema: true
```
!!! warning

    If you want to merge the schema, then the same doctrine connection must be used as with the doctrine orm. 
    Otherwise errors may occur!
    
!!! note

    All schema relevant commands are removed if you activate this option. You should use the doctrine commands then.
    
!!! tip

    If you want to learn more about store, read the [library documentation](https://event-sourcing.patchlevel.io/latest/store/).
    
### Kernel Reset

Only available in `in_memory` store. If you want to reset the store after each kernel request, you can activate this option.
So you can avoid side effects between the tests.

```yaml
patchlevel_event_sourcing:
    store:
        kernel_reset: true
```
### Data Migration

If you want to migrate from your current store to a new store, you can use the following configuration.
This register a new store and a new cli command `event-sourcing:store:migrate`.
You can define translators to translate the old events to the new store.
Here is an example for a migration from `dbal_aggregate` to `dbal_stream`.

```yaml
patchlevel_event_sourcing:
    store:
        migrate_to_new_store:
            type: 'dbal_stream'
            options:
                table_name: 'my_stream_store'
            translators:
              - Patchlevel\EventSourcing\Message\Translator\AggregateToStreamHeaderTranslator
```
!!! danger

    Make sure that you use different table names for the old and new store.
    Otherwise your event store will be destroyed.
    
!!! tip

    Set the `read_only` flag to `true` for the old store to avoid side effects
    and missing events during the migration.
    
## Migration

You can use [doctrine migrations](https://www.doctrine-project.org/projects/migrations.html) to manage the schema.

```yaml
patchlevel_event_sourcing:
    migration:
        namespace: EventSourcingMigrations
        path: "%kernel.project_dir%/migrations"
```
## Subscription

!!! tip

    You can find out more about subscriptions in the library 
    [documentation](https://event-sourcing.patchlevel.io/latest/subscription/).
    
### Store

You can change where the subscription engine stores its necessary information about the subscription.
Default is `dbal`, which means it stores it in the same DB that is used by the dbal event store.

Otherwise you can choose between the following stores:

- `dbal` *default*
- `in_memory`
- `static_in_memory`
- `custom`

```yaml
patchlevel_event_sourcing:
  subscription:
    store: 
      type: 'custom' # default is 'dbal'
      service: 'my_subscription_store'
```
!!! tip

    If you are using the [doctrine-test-bundle](https://github.com/dmaicher/doctrine-test-bundle),
    you can use the `static_in_memory` store for testing.
    
### Catch Up

If aggregates are used in the processors and new events are generated there,
then they are not part of the current subscription engine `run` and will only be processed during the next run or boot.
This is usually not a problem in dev or prod environment because a worker is used
and these events will be processed at some point. But in testing it is not so easy.
For this reason, you can activate the `catch_up` option.

```yaml
patchlevel_event_sourcing:
    subscription:
        catch_up: true
```
### Throw on Error

You can activate the `throw_on_error` option to throw an exception if a subscription engine run has an error.
This is useful for testing or development to get directly feedback if something is wrong.

```yaml
patchlevel_event_sourcing:
    subscription:
        throw_on_error: true
```
!!! warning

    This option should not be used in production. The normal behavior is to log the error and continue.
    
### Run After Aggregate Save

If you want to run the subscription engine after an aggregate is saved, you can activate this option.
This is useful for testing or development, so you don't have run a worker to process the events.

```yaml
patchlevel_event_sourcing:
    subscription:
        run_after_aggregate_save: true
```
### Auto Setup

If you want to automatically setup the subscription engine, you can activate this option.
This is useful for development, so you don't have to setup the subscription engine manually.

```yaml
patchlevel_event_sourcing:
    subscription:
        auto_setup: true
```
!!! note

    This works only before each http requests and not if you use the console commands.
    
### Rebuild After File Change

If you want to rebuild the subscription engine after a file change, you can activate this option.
This is also useful for development, so you don't have to rebuild the projections manually.

```yaml
patchlevel_event_sourcing:
    subscription:
        rebuild_after_file_change: true
```
!!! note

    This works only before each http requests and not if you use the console commands.
    
!!! tip

    This is using the cache system to store the latest file change time. You can change the cache pool with the `cache_pool` option.
    
### Gap Detection

Depending on the database you are using for the eventstore it may be happening that your subscriptions are skipping some
events. This is due to how auto-increments work in these databases in combination with e.g. longer open transactions.
Even when not working with longer open transactions, this may occur if load is high on the database. We already have a
locking mechanism in place to prevent this behaviour which throttles write speed. Gap Detection operates different, it
checks if a gap between the last message handled and the current message is present. If so it waits a reasonable amount
of time and re-fetches the message. This results into slower updates for the subscriptions but creates more resilience.

```yaml
patchlevel_event_sourcing:
    subscription:
        gap_detection: ~
```
!!! info

    For more context you can read more about this in [this issue](https://github.com/patchlevel/event-sourcing/issues/727#issuecomment-2757297536).
    
!!! tip

    You can use both techniques locking and gap detecion to mitigate gaps happening in the subscriptions.
    
You can also define how often the gap detection should re-check the gap and how long it should wait, in this example we
instantly retry the first time, then we wait 500ms and after that we check a last time after 1 second.

```yaml
patchlevel_event_sourcing:
    subscription:
        gap_detection:
            retries_in_ms: [0, 500, 1000]
```
Another config option is to define the detection window. The option defines the timeframe from now if we should check
for a gap. It's defined as an [DateInterval](https://www.php.net/manual/en/class.dateinterval.php) so you need to
provide a valid `string` for it.

```yaml
patchlevel_event_sourcing:
    subscription:
        gap_detection:
            detection_window: 'PT5M'
```
## Command Bus

You can enable the command bus integration to use your aggregates as command handlers.
For this bundle we provide only a symfony messenger integration, so you have to define the bus in the messenger configuration.

```yaml
framework:
    messenger:
        default_bus: command.bus
        buses:
            command.bus: ~
```
After this, you need to define the command bus in the event sourcing configuration.
This will automatically register the aggregate handlers for the symfony messenger,
so you can handle commands with your aggregates.

```yaml
patchlevel_event_sourcing:
    command_bus:
        service: command.bus
```
!!! note

    You can find out more about the command bus and the aggregate handlers [here](https://event-sourcing.patchlevel.io/latest/command_bus/).
    
## Query Bus

You can enable the query bus integration to use queries to retrieve data from your system. For this bundle we provide
only a symfony messenger integration, so you have to define the bus in the messenger configuration.

```yaml
framework:
    messenger:
        buses:
            query.bus: ~
```
After this, you need to define the query bus in the event sourcing configuration.
This will automatically register the handlers for the symfony messenger, so you can handle queries in your services.

```yaml
patchlevel_event_sourcing:
    query_bus:
        service: query.bus
```
!!! note

    You can find out more about the query bus [here](https://event-sourcing.patchlevel.io/latest/query_bus/).
    
## Event Bus

You can enable the event bus to listen for events and messages synchronously.
But you should consider using the subscription engine for this.

```yaml
patchlevel_event_sourcing:
    event_bus: ~
```
!!! note

    Default is the patchlevel [event bus](https://event-sourcing.patchlevel.io/latest/event_bus/).
    
### Patchlevel (Default) Event Bus

First of all we have our own default event bus.
This works best with the library, as the `#[Subscribe]` attribute is used there, among other things.

```yaml
patchlevel_event_sourcing:
    event_bus:
        type: default
```
!!! note

    You don't have to specify this as it is the default value.
    
### Symfony Event Bus

But you can also use [Symfony Messenger](https://symfony.com/doc/current/messenger.html).
To do this, you first have to define a suitable message bus.
This must be "allow_no_handlers" so that this messenger can be an event bus according to the definition.

```yaml
# messenger.yaml
framework:
    messenger:
        buses:
            event.bus:
                default_middleware: allow_no_handlers
```
We can then use this messenger or event bus in event sourcing:

```yaml
patchlevel_event_sourcing:
    event_bus:
        service: event.bus
```
Since the event bus was replaced, event sourcing own attributes no longer work.
You use the Symfony attributes instead.

```php
use Patchlevel\EventSourcing\EventBus\Message;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler('event.bus')]
class SmsNotificationHandler
{
    public function __invoke(Message $message): void
    {
        if (!$message instanceof GuestIsCheckedIn) {
            return;
        }

        // ... do some work - like sending an SMS message!
    }
}
```
### PSR-14 Event Bus

You can also use any other event bus that implements the [PSR-14](https://www.php-fig.org/psr/psr-14/) standard.

```yaml
patchlevel_event_sourcing:
    event_bus:
        type: psr14
        service: my.event.bus.service
```
!!! note

    Like the Symfony event bus, the event sourcing attributes no longer work here.
    You have to use the system that comes with the respective psr14 implementation.
    
### Custom Event Bus

You can also use your own event bus that implements the `Patchlevel\EventSourcing\EventBus\EventBus` interface.

```yaml
patchlevel_event_sourcing:
    event_bus:
        type: custom
        service: my.event.bus.service
```
!!! note

    Like the Symfony event bus, the event sourcing attributes no longer work here.
    You have to use the system that comes with the respective custom implementation.
    
## Snapshot

You can use symfony cache to define the target of the snapshot store.

```yaml
framework:
    cache:
        default_redis_provider: 'redis://localhost'
        pools:
            event_sourcing.cache:
                adapter: cache.adapter.redis
```
After this, you need define the snapshot store.
Symfony cache implement the psr6 interface, so we need choose this type
and enter the id from the cache service.

```yaml
patchlevel_event_sourcing:
    snapshot_stores:
        default:
            service: event_sourcing.cache
```
Finally, you have to tell the aggregate that it should use this snapshot store.

```php
namespace App\Profile\Domain;

use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Snapshot;

#[Aggregate(name: 'profile')]
#[Snapshot('default')]
final class Profile extends BasicAggregateRoot
{
    // ...
}
```
!!! note

    You can find out more about snapshots [here](https://event-sourcing.patchlevel.io/latest/snapshots/).
    
## Cryptography

You can use the library to encrypt and decrypt personal data.
For this you need to enable the crypto shredding.

```yaml
patchlevel_event_sourcing:
    cryptography:
      use_encrypted_field_name: true,
```

!!! tip

    You should activate `use_encrypted_field_name` to mark the fields that are encrypted.
    That allows you later to migrate not encrypted fields to encrypted fields.
    If you have already encrypted fields, you can activate `fallback_to_field_name` to use the old field name as fallback.

If you want to use another algorithm, you can specify this here:

```yaml
patchlevel_event_sourcing:
    cryptography:
        algorithm: 'aes-256-gcm'
```
!!! note

    You can find out more about personal data [here](https://event-sourcing.patchlevel.io/latest/personal_data/).
    
## Clock

The clock is used to return the current time as DateTimeImmutable.

### Freeze Clock

You can freeze the clock for testing purposes:

```yaml
when@test:
    patchlevel_event_sourcing:
        clock:
            freeze: '2020-01-01 22:00:00'
```
!!! note

    If freeze is not set, then the system clock is used.
    
### Symfony Clock

Since symfony 6.2 there is a [clock](https://symfony.com/doc/current/components/clock.html) implementation
based on psr-20 that you can use.

```bash
composer require symfony/clock
```
```yaml
patchlevel_event_sourcing:
    clock:
        service: 'clock'
```
### PSR-20

You can also use your own implementation of your choice.
They only have to implement the interface of the [psr-20](https://www.php-fig.org/psr/psr-20/).
You can then specify this service here:

```yaml
patchlevel_event_sourcing:
    clock:
        service: 'my_own_clock_service'
```